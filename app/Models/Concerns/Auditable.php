<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Journalise création, modification, suppression (douce) et restauration
 * dans audit_logs : qui, quoi, anciennes / nouvelles valeurs, adresse IP.
 *
 * Les attributs listés dans $auditExclude (ou masqués par $hidden) ne sont
 * jamais écrits dans le journal.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->writeAudit('created', null, $model->auditableAttributes($model->getAttributes())));

        static::updated(function ($model) {
            $changes = $model->auditableAttributes($model->getChanges());
            // deleted_at est journalisé par les événements deleted / restored.
            unset($changes['updated_at'], $changes['deleted_at']);

            if ($changes === []) {
                return;
            }

            $old = array_intersect_key($model->auditableAttributes($model->getRawOriginal()), $changes);

            $model->writeAudit('updated', $old, $changes);
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            // delete() en suppression douce ne déclenche pas "updated" : on journalise ici.
            static::deleted(function ($model) {
                if ($model->isForceDeleting()) {
                    $model->writeAudit('force_deleted', $model->auditableAttributes($model->getRawOriginal()), null);
                } else {
                    $model->writeAudit('deleted', null, ['deleted_at' => $model->{$model->getDeletedAtColumn()}?->toDateTimeString()]);
                }
            });

            static::restored(fn ($model) => $model->writeAudit('restored', null, ['deleted_at' => null]));
        } else {
            static::deleted(fn ($model) => $model->writeAudit('deleted', $model->auditableAttributes($model->getRawOriginal()), null));
        }
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    protected function writeAudit(string $action, ?array $old, ?array $new): void
    {
        AuditLog::record($action, $this, $old, $new);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableAttributes(array $attributes): array
    {
        $excluded = array_merge(
            $this->getHidden(),
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
        );

        return array_diff_key($attributes, array_flip($excluded));
    }
}
