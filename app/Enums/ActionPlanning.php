<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Action appliquée lors d'une basculade / report en masse (§4.3).
 */
enum ActionPlanning: string
{
    use HasValues;

    case ReaffecterConfrere = 'reaffecter_confrere';
    case ReporterProchaineVacation = 'reporter_prochaine_vacation';
    case AReplanifier = 'a_replanifier';
}
