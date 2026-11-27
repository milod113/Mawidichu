<?php

namespace App\Models;

use App\Enums\StatutAvis;
use App\Enums\TypeAvis;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PreventsForceDelete;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DemandeAvis extends Model
{
    use Auditable, PreventsForceDelete, SoftDeletes;

    protected $table = 'demandes_avis';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type_avis' => TypeAvis::class,
            'statut' => StatutAvis::class,
            'expire_le' => 'datetime',
        ];
    }

    /**
     * Demandes dont l'autorisation d'accès temporaire est encore valable.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expire_le')->orWhere('expire_le', '>', now());
        });
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function serviceDemandeur(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_demandeur_id');
    }

    public function medecinDemandeur(): BelongsTo
    {
        return $this->belongsTo(Medecin::class, 'medecin_demandeur_id');
    }

    public function serviceCible(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_cible_id');
    }

    public function medecinCible(): BelongsTo
    {
        return $this->belongsTo(Medecin::class, 'medecin_cible_id');
    }
}
