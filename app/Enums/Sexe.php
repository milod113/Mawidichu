<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Sexe du patient.
 */
enum Sexe: string
{
    use HasValues;

    case Masculin = 'M';
    case Feminin = 'F';
}
