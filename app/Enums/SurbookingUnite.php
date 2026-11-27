<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Unité de la tolérance de surbooking.
 */
enum SurbookingUnite: string
{
    use HasValues;

    case Nombre = 'nombre';
    case Pourcentage = 'pourcentage';
}
