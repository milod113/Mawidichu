<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Type de jour férié (§4.3).
 */
enum TypeJourFerie: string
{
    use HasValues;

    case Fixe = 'fixe';
    case Mobile = 'mobile';
}
