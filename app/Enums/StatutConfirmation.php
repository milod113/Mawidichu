<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Statut de confirmation d'un jour férié lunaire (§4.3).
 */
enum StatutConfirmation: string
{
    use HasValues;

    case Confirme = 'confirme';
    case Estimatif = 'estimatif';
}
