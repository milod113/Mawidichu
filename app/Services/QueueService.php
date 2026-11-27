<?php

namespace App\Services;

use App\Enums\Priorite;
use App\Enums\StatutRdv;
use App\Events\ServiceActivity;
use App\Models\AuditLog;
use App\Models\ConstantePreconsultation;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Parcours du patient dans la journée (§5.1) :
 * planifie → arrive → [en_preconsultation → pret_medecin] → appele → en_consultation → termine
 * (ou absent / reporte / en_attente_retour). Chaque transition est contrôlée,
 * horodatée et journalisée.
 */
class QueueService
{
    /** Statuts d'un patient présent dans l'établissement et pas encore appelé. */
    public const EN_SALLE = [StatutRdv::Arrive, StatutRdv::EnPreconsultation, StatutRdv::PretMedecin];

    /**
     * Pointage à l'arrivée (ou retour d'un patient « en attente de retour »).
     */
    public function checkIn(RendezVous $rdv): RendezVous
    {
        if (! $rdv->date_rdv->isToday()) {
            throw new QueueException('file.erreur.pas_aujourdhui');
        }
        $this->exiger($rdv, [StatutRdv::Planifie, StatutRdv::EnAttenteRetour]);

        $rdv->update(['statut' => StatutRdv::Arrive, 'heure_arrivee_checkin' => now()]);
        $this->signaler($rdv);

        return $rdv;
    }

    /**
     * Badge de priorité : toujours accompagné d'un motif et tracé dans audit_logs.
     */
    public function appliquerPriorite(RendezVous $rdv, Priorite $priorite, ?string $motif): RendezVous
    {
        if ($priorite !== Priorite::Normal && blank($motif)) {
            throw new QueueException('rdv.erreur.motif_priorite');
        }
        $this->exiger($rdv, [StatutRdv::Planifie, StatutRdv::Arrive, StatutRdv::EnPreconsultation, StatutRdv::PretMedecin, StatutRdv::EnAttenteRetour]);

        $avant = ['priorite' => $rdv->priorite->value, 'motif' => $rdv->motif_priorite];
        $rdv->update(['priorite' => $priorite, 'motif_priorite' => $priorite === Priorite::Normal ? null : $motif]);
        AuditLog::record('priorite_appliquee', $rdv, $avant, ['priorite' => $priorite->value, 'motif' => $motif]);
        $this->signaler($rdv);

        return $rdv;
    }

    public function marquerAbsent(RendezVous $rdv): RendezVous
    {
        $this->exiger($rdv, [StatutRdv::Planifie, StatutRdv::Arrive, StatutRdv::EnAttenteRetour]);

        DB::transaction(function () use ($rdv) {
            $rdv->update(['statut' => StatutRdv::Absent]);
            $rdv->patient()->increment('nb_absences');
        });
        $this->signaler($rdv);

        return $rdv;
    }

    public function demarrerPreconsultation(RendezVous $rdv): RendezVous
    {
        $this->exigerPreconsultation($rdv);
        $this->exiger($rdv, [StatutRdv::Arrive]);

        $rdv->update(['statut' => StatutRdv::EnPreconsultation]);
        $this->signaler($rdv);

        return $rdv;
    }

    /**
     * Enregistre les constantes puis rend le patient disponible pour le médecin.
     *
     * @param  array<string, mixed>  $constantes
     */
    public function terminerPreconsultation(RendezVous $rdv, User $infirmier, array $constantes): RendezVous
    {
        $this->exigerPreconsultation($rdv);
        $this->exiger($rdv, [StatutRdv::Arrive, StatutRdv::EnPreconsultation, StatutRdv::PretMedecin]);

        DB::transaction(function () use ($rdv, $infirmier, $constantes) {
            ConstantePreconsultation::withTrashed()->updateOrCreate(
                ['rdv_id' => $rdv->id],
                [...$constantes, 'infirmier_id' => $infirmier->id, 'deleted_at' => null],
            );
            $rdv->update(['statut' => StatutRdv::PretMedecin]);
        });
        $this->signaler($rdv);

        return $rdv;
    }

    /**
     * Patients présents, dans l'ordre de passage : rang de priorité (configurable)
     * puis heure réelle de check-in.
     *
     * @param  Collection<int, RendezVous>  $rdvs
     * @return Collection<int, RendezVous>
     */
    public function ordonner(Collection $rdvs): Collection
    {
        $rangs = config('mawid.queue_priority_rank');

        return $rdvs->sortBy([
            fn (RendezVous $a, RendezVous $b) => ($rangs[$b->priorite->value] ?? 0) <=> ($rangs[$a->priorite->value] ?? 0),
            fn (RendezVous $a, RendezVous $b) => $a->heure_arrivee_checkin <=> $b->heure_arrivee_checkin,
        ])->values();
    }

    /**
     * @param  list<StatutRdv>  $autorises
     */
    private function exiger(RendezVous $rdv, array $autorises): void
    {
        if (! in_array($rdv->statut, $autorises, true)) {
            throw new QueueException('file.erreur.statut');
        }
    }

    private function exigerPreconsultation(RendezVous $rdv): void
    {
        if (! $rdv->service->preconsultation_active) {
            throw new QueueException('file.erreur.preconsultation_inactive');
        }
    }

    private function signaler(RendezVous $rdv): void
    {
        ServiceActivity::dispatch($rdv->service_id, 'file', $rdv->date_rdv->toDateString());
    }
}
