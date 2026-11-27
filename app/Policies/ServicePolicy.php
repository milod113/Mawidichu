<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Service;
use App\Models\User;

/**
 * Super admin : tout (Gate::before). Chef de service : uniquement son service,
 * sans pouvoir créer ni supprimer de service.
 */
class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([RoleName::SuperAdmin->value, RoleName::ChefService->value]);
    }

    public function view(User $user, Service $service): bool
    {
        return $user->canManageService($service);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Service $service): bool
    {
        return $user->canManageService($service);
    }

    public function delete(User $user, Service $service): bool
    {
        return false;
    }

    /**
     * Gérer le contenu du service : box, types de consultation, médecins, plannings.
     */
    public function manage(User $user, Service $service): bool
    {
        return $user->canManageService($service);
    }
}
