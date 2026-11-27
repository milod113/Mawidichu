<?php

namespace App\Models;

use App\Enums\Sexe;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PreventsForceDelete;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Identité administrative partagée entre services (IPP unique). L'historique
 * médical, lui, reste cloisonné par service (voir Consultation::scopeForService).
 */
class Patient extends Model
{
    use Auditable, HasFactory, PreventsForceDelete, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'sexe' => Sexe::class,
            'nb_absences' => 'integer',
        ];
    }

    public function rendezVous(): HasMany
    {
        return $this->hasMany(RendezVous::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DocumentPatient::class);
    }

    public function demandesAvis(): HasMany
    {
        return $this->hasMany(DemandeAvis::class);
    }
}
