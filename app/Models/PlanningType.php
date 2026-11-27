<?php

namespace App\Models;

use App\Enums\ModePlanification;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Vacation hebdomadaire type (planning récurrent) d'un médecin ou d'un box.
 */
class PlanningType extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'plannings_types';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'jour_semaine' => 'integer',
            'mode_planification' => ModePlanification::class,
            'quota_rdv' => 'integer',
            'quota_sans_rdv' => 'integer',
            'taille_vague' => 'integer',
            'intervalle_vague_min' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function medecin(): BelongsTo
    {
        return $this->belongsTo(Medecin::class);
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class);
    }
}
