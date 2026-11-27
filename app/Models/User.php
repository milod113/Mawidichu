<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleName;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'telephone',
        'is_active',
        'locale',
        'affectation_service_id',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Le super administrateur gère tout ; un chef de service gère son propre service.
     */
    public function canManageService(Service|int $service): bool
    {
        if ($this->hasRole(RoleName::SuperAdmin->value)) {
            return true;
        }

        $id = $service instanceof Service ? $service->getKey() : $service;

        return $this->hasRole(RoleName::ChefService->value)
            && $this->medecin !== null
            && $this->medecin->service_id === $id;
    }

    public function affectationService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'affectation_service_id');
    }

    /**
     * Le poste voit-il ce service ? Direction et super admin : tous ; guichet central
     * (secrétaire sans affectation) : tous ; sinon uniquement son propre service.
     */
    public function canAccessService(Service|int $service): bool
    {
        $id = $service instanceof Service ? $service->getKey() : $service;

        if ($this->hasAnyRole([RoleName::SuperAdmin->value, RoleName::Direction->value])) {
            return true;
        }

        if ($this->hasRole(RoleName::Secretaire->value) && $this->affectation_service_id === null) {
            return true;
        }

        $propre = $this->medecin?->service_id ?? $this->affectation_service_id;

        return $propre !== null && $propre === $id;
    }

    public function medecin(): HasOne
    {
        return $this->hasOne(Medecin::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
