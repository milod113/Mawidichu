<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Box extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'boxes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'numero_box' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function medecinActuel(): BelongsTo
    {
        return $this->belongsTo(Medecin::class, 'medecin_actuel_id');
    }

    public function planningsTypes(): HasMany
    {
        return $this->hasMany(PlanningType::class);
    }

    public function rendezVous(): HasMany
    {
        return $this->hasMany(RendezVous::class);
    }
}
