<?php

namespace Database\Factories;

use App\Enums\CodeConsultation;
use App\Models\Service;
use App\Models\TypeConsultation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TypeConsultation>
 */
class TypeConsultationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'code' => CodeConsultation::PremiereConsultation,
            'libelle' => 'Première consultation',
            'duree_minutes' => 20,
            'is_active' => true,
        ];
    }
}
