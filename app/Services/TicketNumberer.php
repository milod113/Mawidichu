<?php

namespace App\Services;

use App\Models\RendezVous;
use App\Models\Service;

/**
 * Numéro de ticket : {code du service}-{séquence du jour}. À appeler dans une
 * transaction : la ligne du service est verrouillée pour éviter les collisions.
 */
class TicketNumberer
{
    public function next(Service $service, string $date): string
    {
        Service::whereKey($service->id)->lockForUpdate()->first();

        $dernier = RendezVous::withTrashed()->where('service_id', $service->id)->whereDate('date_rdv', $date)
            ->where('numero_ticket', 'like', $service->code.'-%')->orderByDesc('numero_ticket')->value('numero_ticket');

        $numero = $dernier ? ((int) substr($dernier, strlen($service->code) + 1)) + 1 : 1;

        return $service->code.'-'.str_pad((string) $numero, config('mawid.ticket_pad'), '0', STR_PAD_LEFT);
    }
}
