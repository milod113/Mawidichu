<?php

namespace Database\Factories;

use App\Enums\Grade;
use App\Models\Medecin;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medecin>
 */
class MedecinFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'service_id' => Service::factory(),
            'grade' => fake()->randomElement(Grade::cases()),
        ];
    }
}
