<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PreventsForceDelete;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consultation extends Model
{
    use Auditable, PreventsForceDelete, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ordonnance_json' => 'array',
            'bilans_demandes_json' => 'array',
            'delai_controle_jours' => 'integer',
        ];
    }

    /**
     * Cloisonnement : n'expose que l'historique médical d'un service donné.
     * Les dérogations (demande d'avis inter-spécialités) seront appliquées
     * par la couche d'autorisation de l'Étape 6.
     */
    public function scopeForService(Builder $query, int|Service $service): Builder
    {
        return $query->where('service_id', $service instanceof Service ? $service->getKey() : $service);
    }

    public function rendezVous(): BelongsTo
    {
        return $this->belongsTo(RendezVous::class, 'rdv_id');
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

    public function superviseurSenior(): BelongsTo
    {
        return $this->belongsTo(Medecin::class, 'superviseur_senior_id');
    }

    public function demandesAvis(): HasMany
    {
        return $this->hasMany(DemandeAvis::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DocumentPatient::class);
    }
}
