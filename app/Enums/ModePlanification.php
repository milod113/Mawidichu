<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Mode de planification (cahier des charges §3.2).
 */
enum ModePlanification: string
{
    use HasValues;

    case QuotaVague = 'quota_vague';
    case CreneauxDynamiques = 'creneaux_dynamiques';
    case Mixte = 'mixte';
}
