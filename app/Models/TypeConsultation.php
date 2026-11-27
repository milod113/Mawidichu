<?php

namespace App\Models;

use App\Enums\CodeConsultation;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TypeConsultation extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'types_consultation';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'code' => CodeConsultation::class,
            'duree_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function rendezVous(): HasMany
    {
        return $this->hasMany(RendezVous::class);
    }
}
