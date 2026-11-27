<?php

namespace Tests\Concerns;

use App\Enums\CodeConsultation;
use App\Enums\ModePlanification;
use App\Enums\RoleName;
use App\Models\Medecin;
use App\Models\PlanningType;
use App\Models\Service;
use App\Models\TypeConsultation;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

/**
 * Jeu de données minimal pour les tests de planification : un service, un
 * médecin, les 3 types de consultation et une vacation le mardi 2030-01-08
 * (le « lundi 2030-01-07 » est la date simulée du jour).
 */
trait BuildsPlanning
{
    protected string $mardi = '2030-01-08';

    protected function fixer(): void
    {
        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2030-01-07 08:00:00'));
    }

    protected function service(array $attrs = []): Service
    {
        return Service::factory()->create($attrs + ['code' => 'CAR']);
    }

    /**
     * @return array<string, TypeConsultation>
     */
    protected function types(Service $service, int $premiere = 20, int $controle = 5, int $bilan = 5): array
    {
        $make = fn (CodeConsultation $c, int $d) => TypeConsultation::factory()->create([
            'service_id' => $service->id, 'code' => $c, 'libelle' => $c->value, 'duree_minutes' => $d,
        ]);

        return [
            'premiere' => $make(CodeConsultation::PremiereConsultation, $premiere),
            'controle' => $make(CodeConsultation::Controle, $controle),
            'bilan' => $make(CodeConsultation::RetourBilan, $bilan),
        ];
    }

    protected function medecin(Service $service, array $attrs = []): Medecin
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Medecin->value);

        return Medecin::factory()->create($attrs + ['user_id' => $user->id, 'service_id' => $service->id]);
    }

    protected function vacation(Service $service, ?Medecin $medecin, array $attrs = []): PlanningType
    {
        return PlanningType::create($attrs + [
            'service_id' => $service->id, 'medecin_id' => $medecin?->id, 'jour_semaine' => 2,
            'heure_debut' => '08:30', 'heure_fin' => '11:30', 'quota_rdv' => 4, 'taille_vague' => 2,
            'intervalle_vague_min' => 60, 'is_active' => true,
        ]);
    }

    protected function secretaire(?Service $affectation = null): User
    {
        $u = User::factory()->create(['affectation_service_id' => $affectation?->id]);

        return $u->assignRole(RoleName::Secretaire->value);
    }

    protected function modeDynamique(): ModePlanification
    {
        return ModePlanification::CreneauxDynamiques;
    }
}
