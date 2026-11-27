<?php

namespace App\Models;

use App\Enums\ActionPlanning;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Absence / fermeture : congé, garde, congrès, férié de dernière minute…
 */
class ExceptionPlanning extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'exceptions_planning';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'action_appliquee' => ActionPlanning::class,
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
}
