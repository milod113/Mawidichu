<?php

namespace App\Services;

use DomainException;

/**
 * Refus métier d'une prise de rendez-vous ; le message est une clé de
 * traduction (lang/ui/*.json) affichée telle quelle à l'utilisateur.
 */
class BookingException extends DomainException {}
