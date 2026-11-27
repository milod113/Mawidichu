<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Contenu affiché sur l'écran TV : ticket ou nom du patient (§5.3).
 */
enum AffichageTvMode: string
{
    use HasValues;

    case Ticket = 'ticket';
    case Nom = 'nom';
}
