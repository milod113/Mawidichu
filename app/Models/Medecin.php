<?php

namespace App\Models;

use App\Enums\Grade;
use App\Enums\SurbookingUnite;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medecin extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'medecins';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'grade' => Grade::class,
            'duree_consult_defaut' => 'integer',
            'quota_max_defaut' => 'integer',
            'tolere_surbooking' => 'boolean',
            'valeur_surbooking' => 'integer',
            'surbooking_unite' => SurbookingUnite::class,
            'medicaments_favoris_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function planningsTypes(): HasMany
    {
        return $this->hasMany(PlanningType::class);
    }

    public function exceptionsPlanning(): HasMany
    {
        return $this->hasMany(ExceptionPlanning::class);
    }

    public function rendezVous(): HasMany
    {
        return $this->hasMany(RendezVous::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }
}
