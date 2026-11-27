<?php

namespace App\Models;

use App\Enums\AffichageTvMode;
use App\Enums\ModeAttributionRdv;
use App\Enums\ModePlanification;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'mode_planification' => ModePlanification::class,
            'mode_attribution_rdv' => ModeAttributionRdv::class,
            'affichage_tv_mode' => AffichageTvMode::class,
            'preconsultation_active' => 'boolean',
            'nb_box' => 'integer',
            'documents_defaut_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function medecins(): HasMany
    {
        return $this->hasMany(Medecin::class);
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(Box::class);
    }

    public function typesConsultation(): HasMany
    {
        return $this->hasMany(TypeConsultation::class);
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
}
