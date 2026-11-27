<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Données médicales / administratives patients : aucune suppression
 * physique (directive n°4 du cahier des charges).
 */
trait PreventsForceDelete
{
    public static function bootPreventsForceDelete(): void
    {
        static::forceDeleting(function ($model) {
            throw new LogicException(sprintf('Suppression définitive interdite sur %s : utiliser la suppression douce.', $model::class));
        });
    }
}
