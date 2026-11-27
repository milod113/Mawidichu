<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * États du rendez-vous dans la journée (§5.1, §5.3).
 */
enum StatutRdv: string
{
    use HasValues;

    case Planifie = 'planifie';
    case Arrive = 'arrive';
    case EnPreconsultation = 'en_preconsultation';
    case PretMedecin = 'pret_medecin';
    case Appele = 'appele';
    case EnConsultation = 'en_consultation';
    case Termine = 'termine';
    case Absent = 'absent';
    case Reporte = 'reporte';
    case EnAttenteRetour = 'en_attente_retour';
    case AReplanifier = 'a_replanifier';
}
