<?php

namespace App\Services;

use DomainException;

/**
 * Transition de statut refusée ; le message est une clé de traduction.
 */
class QueueException extends DomainException {}
