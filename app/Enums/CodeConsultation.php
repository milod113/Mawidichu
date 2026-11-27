<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Flux de consultation (§3.1).
 */
enum CodeConsultation: string
{
    use HasValues;

    case PremiereConsultation = 'premiere_consultation';
    case Controle = 'controle';
    case RetourBilan = 'retour_bilan';
}
