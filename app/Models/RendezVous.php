<?php

namespace App\Models;

use App\Enums\Priorite;
use App\Enums\StatutRdv;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PreventsForceDelete;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class RendezVous extends Model
{
    use Auditable, HasFactory, PreventsForceDelete, SoftDeletes;

    protected $table = 'rendez_vous';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date_rdv' => 'date',
            'heure_arrivee_checkin' => 'datetime',
            'heure_appel' => 'datetime',
            'statut' => StatutRdv::class,
            'priorite' => Priorite::class,
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
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

    public function typeConsultation(): BelongsTo
    {
        return $this->belongsTo(TypeConsultation::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function constantes(): HasOne
    {
        return $this->hasOne(ConstantePreconsultation::class, 'rdv_id');
    }

    public function consultation(): HasOne
    {
        return $this->hasOne(Consultation::class, 'rdv_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DocumentPatient::class, 'rdv_id');
    }
}
