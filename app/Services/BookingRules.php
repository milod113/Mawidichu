<?php

namespace App\Services;

use App\Enums\CodeConsultation;
use App\Enums\ModeAttributionRdv;
use App\Enums\RoleName;
use App\Models\Service;
use App\Models\TypeConsultation;
use App\Models\User;

/**
 * Qui peut attribuer quel rendez-vous (cahier des charges §4.1) :
 * - centralisé : le guichet central (secrétaire sans affectation) seulement ;
 * - décentralisé : le secrétariat du service seulement ;
 * - hybride : le guichet central pour les premières consultations, le
 *   secrétariat du service et le médecin pour les contrôles / retours de bilan.
 */
class BookingRules
{
    public function canBook(User $user, Service $service, CodeConsultation $code): bool
    {
        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return true;
        }

        if ($user->hasRole(RoleName::Secretaire->value)) {
            $central = $user->affectation_service_id === null;
            $duService = $user->affectation_service_id === $service->id;

            return match ($service->mode_attribution_rdv) {
                ModeAttributionRdv::Centralise => $central,
                ModeAttributionRdv::Decentralise => $duService,
                ModeAttributionRdv::Hybride => $code === CodeConsultation::PremiereConsultation ? $central : $duService,
            };
        }

        // Circuit direct : le médecin programme le contrôle de ses propres patients.
        if ($user->hasAnyRole([RoleName::Medecin->value, RoleName::ChefService->value])) {
            return $code !== CodeConsultation::PremiereConsultation
                && $user->medecin?->service_id === $service->id;
        }

        return false;
    }

    public function canBookType(User $user, TypeConsultation $type): bool
    {
        return $this->canBook($user, $type->service, $type->code);
    }
}
