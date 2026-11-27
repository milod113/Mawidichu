<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Statut d'une demande d'avis.
 */
enum StatutAvis: string
{
    use HasValues;

    case EnAttente = 'en_attente';
    case Repondu = 'repondu';
}
