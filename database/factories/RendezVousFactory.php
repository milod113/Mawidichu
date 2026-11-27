<?php

namespace Database\Factories;

use App\Enums\Priorite;
use App\Enums\StatutRdv;
use App\Models\Patient;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\TypeConsultation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RendezVous>
 */
class RendezVousFactory extends Factory
{
    public function definition(): array
    {
        return [
            'numero_ticket' => fake()->unique()->numerify('A-####'),
            'patient_id' => Patient::factory(),
            'service_id' => Service::factory(),
            'type_consultation_id' => fn (array $attrs) => TypeConsultation::factory()->create(['service_id' => $attrs['service_id']])->id,
            'date_rdv' => now()->addDays(7)->toDateString(),
            'statut' => StatutRdv::Planifie,
            'priorite' => Priorite::Normal,
            'created_by' => User::factory(),
        ];
    }
}
