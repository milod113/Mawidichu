<?php

namespace Database\Factories;

use App\Enums\ModeAttributionRdv;
use App\Enums\ModePlanification;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => 'Service '.fake()->unique()->word(),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'mode_planification' => ModePlanification::QuotaVague,
            'mode_attribution_rdv' => ModeAttributionRdv::Hybride,
            'nb_box' => 1,
        ];
    }
}
