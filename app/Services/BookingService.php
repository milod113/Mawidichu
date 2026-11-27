<?php

namespace App\Services;

use App\Enums\Priorite;
use App\Enums\StatutRdv;
use App\Events\ServiceActivity;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PlanningType;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\TypeConsultation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Création d'un rendez-vous : droits, disponibilité recalculée sous verrou de
 * base de données, numéro de ticket, traçabilité de la priorité.
 */
class BookingService
{
    public function __construct(
        private AvailabilityService $availability,
        private SlotLockService $locks,
        private BookingRules $rules,
        private TicketNumberer $numerotation,
    ) {}

    public function book(
        User $user,
        Patient $patient,
        TypeConsultation $type,
        int $planningId,
        string $date,
        Priorite $priorite = Priorite::Normal,
        ?string $motifPriorite = null,
        ?string $heure = null,
        bool $surbooking = false,
    ): RendezVous {
        $service = $type->service;

        if (! $this->rules->canBook($user, $service, $type->code)) {
            throw new BookingException('rdv.erreur.droits');
        }
        if (! $type->is_active || ! $service->is_active) {
            throw new BookingException('rdv.erreur.inactif');
        }
        if ($priorite !== Priorite::Normal && blank($motifPriorite)) {
            throw new BookingException('rdv.erreur.motif_priorite');
        }

        $rdv = DB::transaction(function () use ($user, $patient, $type, $service, $planningId, $date, $priorite, $motifPriorite, $heure, $surbooking) {
            // Sérialise les réservations concurrentes sur la même vacation.
            $planning = PlanningType::where('service_id', $service->id)->lockForUpdate()->find($planningId);
            if (! $planning) {
                throw new BookingException('rdv.erreur.indisponible');
            }

            $entry = $this->availability
                ->forService($service, $type, $date, $date, $planning->medecin, $priorite)
                ->firstWhere('planning_id', $planning->id);

            if (! $entry || $entry['date'] !== $date) {
                throw new BookingException('rdv.erreur.indisponible');
            }

            $disponible = $entry['remaining'] + ($surbooking ? $entry['surbooking_remaining'] : 0);
            $parCreneau = $entry['slots'] !== null;
            $creneau = $parCreneau ? $heure : null;

            if ($parCreneau && ! in_array($heure, $entry['slots'], true)) {
                throw new BookingException('rdv.erreur.creneau');
            }
            if ($disponible - $this->locks->others($planning->id, $date, $creneau, $user->id) <= 0 && ! $parCreneau) {
                throw new BookingException($entry['remaining'] > 0 ? 'rdv.erreur.verrou' : 'rdv.erreur.complet');
            }
            if ($parCreneau && $this->locks->others($planning->id, $date, $creneau, $user->id) > 0) {
                throw new BookingException('rdv.erreur.verrou');
            }

            $doublon = RendezVous::where('patient_id', $patient->id)->where('service_id', $service->id)
                ->whereDate('date_rdv', $date)->whereNotIn('statut', config('mawid.statuts_liberant_place'))->exists();
            if ($doublon) {
                throw new BookingException('rdv.erreur.doublon');
            }

            $rdv = RendezVous::create([
                'numero_ticket' => $this->numerotation->next($service, $date),
                'patient_id' => $patient->id,
                'service_id' => $service->id,
                'medecin_id' => $planning->medecin_id,
                'box_id' => $planning->box_id,
                'type_consultation_id' => $type->id,
                'date_rdv' => $date,
                'heure_vague_prevue' => ($parCreneau ? $heure : $entry['next_time']).':00',
                'statut' => StatutRdv::Planifie,
                'priorite' => $priorite,
                'motif_priorite' => $priorite === Priorite::Normal ? null : $motifPriorite,
                'created_by' => $user->id,
            ]);

            if ($priorite !== Priorite::Normal) {
                AuditLog::record('priorite_appliquee', $rdv, null, ['priorite' => $priorite->value, 'motif' => $motifPriorite]);
            }

            $this->locks->release($planning->id, $date, $creneau, $user->id);

            return $rdv;
        });

        ServiceActivity::emit($service->id, 'quota', $date);

        return $rdv;
    }
}
