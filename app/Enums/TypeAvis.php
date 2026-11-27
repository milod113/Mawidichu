<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Type d'avis demandé (§6.1).
 */
enum TypeAvis: string
{
    use HasValues;

    case SeniorInterne = 'senior_interne';
    case InterSpecialite = 'inter_specialite';
}
