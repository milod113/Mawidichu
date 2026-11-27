<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Badge de priorité, toujours journalisé dans audit_logs (§5.2).
 */
enum Priorite: string
{
    use HasValues;

    case Normal = 'normal';
    case PersonnelChu = 'personnel_chu';
    case OrientationInterServices = 'orientation_inter_services';
    case HorsWilaya = 'hors_wilaya';
    case PersonneAgeeEnceinte = 'personne_agee_enceinte';
}
