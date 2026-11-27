<?php

namespace App\Services;

use App\Enums\Priorite;
use App\Enums\StatutRdv;
use App\Events\PatientCalled;
use App\Events\ServiceActivity;
use App\Models\Consultation;
use App\Models\Medecin;
use App\Models\Patient;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Parcours médecin : appel (affiché sur l'écran TV), consultation, clôture et
 * programmation éventuelle du contrôle.
 */
class ConsultationService
{
    public function __construct(private QueueService $queue, private BookingService $booking) {}

    /**
     * Prochain patient prêt : rang de priorité puis heure d'arrivée, de préférence
     * ceux attribués à ce médecin ou sans médecin.
     */
    public function suivant(Medecin $medecin): ?RendezVous
    {
        $candidats = RendezVous::where('service_id', $medecin->service_id)->whereDate('date_rdv', today())
            ->whereIn('statut', $this->appelables($medecin))
            ->where(fn ($q) => $q->whereNull('medecin_id')->orWhere('medecin_id', $medecin->id))
            ->with('patient:id,nom,prenom')->get();

        return $this->queue->ordonner($candidats)->first();
    }

    public function appeler(RendezVous $rdv, Medecin $medecin): RendezVous
    {
        $this->exigerService($rdv, $medecin);
        if (! in_array($rdv->statut, $this->appelables($medecin), true)) {
            throw new QueueException('file.erreur.statut');
        }

        DB::transaction(fn () => $rdv->update([
            'statut' => StatutRdv::Appele, 'heure_appel' => now(),
            'medecin_id' => $medecin->id, 'box_id' => $rdv->box_id ?? $medecin->planningsTypes()->where('jour_semaine', today()->dayOfWeek)->whereNotNull('box_id')->value('box_id'),
        ]));

        $this->diffuser($rdv);

        return $rdv->refresh();
    }

    /**
     * Nouvel appel (écran TV + signal sonore) d'un patient déjà appelé.
     */
    public function rappeler(RendezVous $rdv, Medecin $medecin): RendezVous
    {
        $this->exigerService($rdv, $medecin);
        if ($rdv->statut !== StatutRdv::Appele) {
            throw new QueueException('file.erreur.statut');
        }
        $rdv->update(['heure_appel' => now()]);
        $this->diffuser($rdv);

        return $rdv;
    }

    /**
     * Le patient ne se présente pas à l'appel : il attend le retour, il n'est pas encore « absent ».
     */
    public function absentALappel(RendezVous $rdv, Medecin $medecin): RendezVous
    {
        $this->exigerService($rdv, $medecin);
        if ($rdv->statut !== StatutRdv::Appele) {
            throw new QueueException('file.erreur.statut');
        }
        $rdv->update(['statut' => StatutRdv::EnAttenteRetour]);
        ServiceActivity::dispatch($rdv->service_id, 'file', $rdv->date_rdv->toDateString());
        PatientCalled::dispatch(['retire' => $rdv->id]);

        return $rdv;
    }

    public function demarrer(RendezVous $rdv, Medecin $medecin): Consultation
    {
        $this->exigerService($rdv, $medecin);
        if (! in_array($rdv->statut, [StatutRdv::Appele, StatutRdv::EnConsultation], true)) {
            throw new QueueException('file.erreur.statut');
        }

        $consultation = DB::transaction(function () use ($rdv, $medecin) {
            $rdv->update(['statut' => StatutRdv::EnConsultation, 'medecin_id' => $medecin->id]);

            return Consultation::withTrashed()->firstOrCreate(['rdv_id' => $rdv->id], [
                'patient_id' => $rdv->patient_id, 'service_id' => $rdv->service_id, 'medecin_id' => $medecin->id,
            ]);
        });
        ServiceActivity::dispatch($rdv->service_id, 'file', $rdv->date_rdv->toDateString());

        return $consultation;
    }

    /**
     * Enregistre la consultation ; en la clôturant, passe le RDV à « terminé » et
     * programme le contrôle : direct (le médecin choisit la vacation) ou assisté
     * (seul le délai est noté, l'accueil planifie).
     *
     * @param  array<string, mixed>  $data
     * @param  array{planning_id: int, date: string, heure?: ?string}|null  $controleDirect
     */
    public function enregistrer(Consultation $consultation, User $user, array $data, bool $cloturer, ?array $controleDirect = null): Consultation
    {
        DB::transaction(function () use ($consultation, $user, $data, $cloturer, $controleDirect) {
            $consultation->update($data);

            if (! $cloturer) {
                return;
            }
            if ($controleDirect !== null) {
                $consultation->update(['controle_rdv_id' => $this->programmerControle($consultation, $user, $controleDirect)->id]);
            }
            $consultation->rendezVous->update(['statut' => StatutRdv::Termine]);
        });

        if ($cloturer) {
            ServiceActivity::dispatch($consultation->service_id, 'file', today()->toDateString());
        }

        return $consultation->refresh();
    }

    /**
     * @param  array{planning_id: int, date: string, heure?: ?string}  $choix
     */
    private function programmerControle(Consultation $consultation, User $user, array $choix): RendezVous
    {
        $type = $consultation->service->typesConsultation()->where('code', 'controle')->where('is_active', true)->first();
        if (! $type) {
            throw new QueueException('consultation.erreur.pas_de_type_controle');
        }

        try {
            return $this->booking->book($user, Patient::findOrFail($consultation->patient_id), $type, $choix['planning_id'], $choix['date'],
                Priorite::Normal, null, $choix['heure'] ?? null);
        } catch (BookingException $e) {
            throw new QueueException($e->getMessage());
        }
    }

    /**
     * @return list<StatutRdv>
     */
    private function appelables(Medecin $medecin): array
    {
        // Avec pré-consultation, seul un patient « prêt » peut être appelé.
        return $medecin->service->preconsultation_active
            ? [StatutRdv::PretMedecin]
            : [StatutRdv::Arrive, StatutRdv::PretMedecin];
    }

    private function exigerService(RendezVous $rdv, Medecin $medecin): void
    {
        if ($rdv->service_id !== $medecin->service_id) {
            throw new QueueException('file.erreur.statut');
        }
    }

    private function diffuser(RendezVous $rdv): void
    {
        $rdv->loadMissing(['service', 'patient', 'box', 'medecin.user']);
        $service = $rdv->service;

        PatientCalled::dispatch([
            'id' => $rdv->id, 'service_id' => $service->id, 'aile' => $service->aile,
            'ticket' => $rdv->numero_ticket,
            'nom' => $service->affichage_tv_mode?->value === 'nom' ? mb_strtoupper($rdv->patient->nom).' '.$rdv->patient->prenom : null,
            'box' => $rdv->box?->nom_affichage ?? ($rdv->box ? 'Box '.$rdv->box->numero_box : null),
            'service_nom' => $service->nom, 'at' => $rdv->heure_appel?->toIso8601String(),
        ]);
        ServiceActivity::dispatch($service->id, 'file', $rdv->date_rdv->toDateString());
    }
}
