<?php

use Illuminate\Support\Facades\Broadcast;

// Activité d'un service (quotas, file d'attente, contrôles à planifier).
Broadcast::channel('service.{id}', fn ($user, $id) => $user->canAccessService((int) $id));

// Écran TV de la salle d'attente : compte kiosque et administrateurs.
Broadcast::channel('tv', fn ($user) => $user->hasAnyRole(['ecran_tv', 'super_admin']));

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});
