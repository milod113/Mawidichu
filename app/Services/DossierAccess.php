<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\Consultation;
use App\Models\DemandeAvis;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Cloisonnement du dossier médical (§6) : un médecin ne lit que les consultations
 * et documents de SON service ; seule une demande d'avis inter-spécialités encore
 * valide ouvre temporairement le dossier du service demandeur au service cible.
 * Direction, secrétaires et infirmiers n'ont aucun accès au contenu médical.
 */
class DossierAccess
{
    /**
     * Identifiants des services dont ce poste peut lire le dossier de ce patient ;
     * null = tous (super administrateur).
     *
     * @return Collection<int, int>|null
     */
    public function serviceIds(User $user, Patient $patient): ?Collection
    {
        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return null;
        }

        $propre = $user->hasAnyRole([RoleName::Medecin->value, RoleName::ChefService->value]) ? $user->medecin?->service_id : null;
        if ($propre === null) {
            return collect();
        }

        $ouverts = DemandeAvis::active()->where('patient_id', $patient->id)
            ->where('service_cible_id', $propre)->where('type_avis', 'inter_specialite')
            ->pluck('service_demandeur_id');

        return $ouverts->push($propre)->unique()->values();
    }

    public function canViewService(User $user, Patient $patient, int $serviceId): bool
    {
        $ids = $this->serviceIds($user, $patient);

        return $ids === null || $ids->contains($serviceId);
    }

    public function canViewConsultation(User $user, Consultation $consultation): bool
    {
        if ($this->canViewService($user, $consultation->patient, $consultation->service_id)) {
            return true;
        }

        // Avis ciblé sur un médecin du même service que le demandeur (senior / interne).
        return $user->medecin !== null && DemandeAvis::active()->where('consultation_id', $consultation->id)
            ->where('medecin_cible_id', $user->medecin->id)->exists();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Consultation>
     */
    public function consultations(User $user, Patient $patient)
    {
        $q = Consultation::where('patient_id', $patient->id);
        $ids = $this->serviceIds($user, $patient);

        return $ids === null ? $q : $q->whereIn('service_id', $ids->all());
    }
}
