<?php

namespace App\Services;

use App\Enums\ActionPlanning;
use App\Enums\StatutRdv;
use App\Events\ServiceActivity;
use App\Models\ExceptionPlanning;
use App\Models\Medecin;
use App\Models\RendezVous;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Basculade d'une absence (§4.3) : les rendez-vous déjà pris sur la période sont
 * réaffectés à un confrère, reportés à la prochaine vacation ou marqués « à replanifier »
 * pour que l'accueil rappelle le patient. Aucun RDV n'est jamais supprimé.
 */
class PlanningDisruptionService
{
    public function __construct(private AvailabilityService $availability, private TicketNumberer $numerotation) {}

    /**
     * Rendez-vous encore planifiés dans la période de l'absence.
     *
     * @return Collection<int, RendezVous>
     */
    public function affectes(ExceptionPlanning $absence): Collection
    {
        return RendezVous::query()
            ->where('service_id', $absence->service_id)
            ->when($absence->medecin_id, fn ($q) => $q->where('medecin_id', $absence->medecin_id))
            ->whereDate('date_rdv', '>=', $absence->date_debut->toDateString())
            ->whereDate('date_rdv', '<=', $absence->date_fin->toDateString())
            ->where('statut', StatutRdv::Planifie)
            ->with(['patient:id,nom,prenom,ipp', 'typeConsultation', 'service'])
            ->orderBy('date_rdv')->orderBy('heure_vague_prevue')
            ->get();
    }

    /**
     * @return array{reaffectes: int, reportes: int, a_replanifier: int}
     */
    public function appliquer(ExceptionPlanning $absence, ActionPlanning $action, ?Medecin $confrere = null): array
    {
        if ($action === ActionPlanning::ReaffecterConfrere
            && ($confrere === null || $confrere->service_id !== $absence->service_id || $confrere->id === $absence->medecin_id)) {
            throw new QueueException('absence.erreur.confrere');
        }

        $bilan = ['reaffectes' => 0, 'reportes' => 0, 'a_replanifier' => 0];

        DB::transaction(function () use ($absence, $action, $confrere, &$bilan) {
            foreach ($this->affectes($absence) as $rdv) {
                $cible = match ($action) {
                    ActionPlanning::ReaffecterConfrere => $this->placer($rdv, $rdv->date_rdv->toDateString(), $rdv->date_rdv->toDateString(), $confrere),
                    ActionPlanning::ReporterProchaineVacation => $this->placer($rdv, $absence->date_fin->copy()->addDay()->toDateString(),
                        $absence->date_fin->copy()->addDays(config('mawid.horizon_jours'))->toDateString(), $rdv->medecin),
                    ActionPlanning::AReplanifier => null,
                };

                if ($cible === null) {
                    $rdv->update(['statut' => StatutRdv::AReplanifier]);
                    $bilan['a_replanifier']++;

                    continue;
                }

                $rdv->update([
                    'medecin_id' => $cible['medecin_id'], 'box_id' => $cible['box_id'], 'date_rdv' => $cible['date'],
                    'heure_vague_prevue' => $cible['heure'].':00',
                    'numero_ticket' => $cible['date'] === $rdv->date_rdv->toDateString() ? $rdv->numero_ticket : $this->numerotation->next($rdv->service, $cible['date']),
                ]);
                $bilan[$action === ActionPlanning::ReaffecterConfrere ? 'reaffectes' : 'reportes']++;
            }

            $absence->update(['action_appliquee' => $action]);
        });

        ServiceActivity::dispatch($absence->service_id, 'quota', $absence->date_debut->toDateString());

        return $bilan;
    }

    /**
     * Première place réellement disponible pour ce RDV dans la fenêtre donnée.
     *
     * @return array{date: string, heure: string, medecin_id: ?int, box_id: ?int}|null
     */
    private function placer(RendezVous $rdv, string $du, string $au, ?Medecin $medecin): ?array
    {
        $entree = $this->availability
            ->forService($rdv->service, $rdv->typeConsultation, $du, $au, $medecin, $rdv->priorite)
            ->first(fn (array $e) => $e['bookable'] && $e['next_time'] !== null);

        return $entree ? [
            'date' => $entree['date'], 'heure' => $entree['next_time'],
            'medecin_id' => $entree['medecin_id'], 'box_id' => $entree['box_id'],
        ] : null;
    }
}
