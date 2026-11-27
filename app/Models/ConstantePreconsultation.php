<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PreventsForceDelete;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConstantePreconsultation extends Model
{
    use Auditable, PreventsForceDelete, SoftDeletes;

    protected $table = 'constantes_preconsultation';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tension_sys' => 'integer',
            'tension_dia' => 'integer',
            'frequence_cardiaque' => 'integer',
            'poids' => 'decimal:2',
            'taille' => 'decimal:1',
            'temperature' => 'decimal:1',
            'glycemie' => 'decimal:2',
        ];
    }

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class, 'rdv_id');
    }

    public function infirmier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'infirmier_id');
    }
}
