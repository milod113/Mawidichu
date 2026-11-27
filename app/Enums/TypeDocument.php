<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Type de document numérisé (§6.2).
 */
enum TypeDocument: string
{
    use HasValues;

    case Orientation = 'orientation';
    case Bilan = 'bilan';
    case Imagerie = 'imagerie';
    case Autre = 'autre';
}
