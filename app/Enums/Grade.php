<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Grade du médecin (§3.3).
 */
enum Grade: string
{
    use HasValues;

    case Pr = 'Pr';
    case MCA = 'MCA';
    case MCB = 'MCB';
    case MA = 'MA';
    case Specialiste = 'Specialiste';
    case Resident = 'Resident';
}
