<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Attribution des RDV entre guichet central et secrétariat de service (§4.1).
 */
enum ModeAttributionRdv: string
{
    use HasValues;

    case Centralise = 'centralise';
    case Decentralise = 'decentralise';
    case Hybride = 'hybride';
}
