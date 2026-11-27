<?php

namespace Database\Factories;

use App\Enums\Sexe;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ipp' => 'IPP'.fake()->unique()->numerify('########'),
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'date_naissance' => fake()->date(),
            'sexe' => fake()->randomElement(Sexe::cases()),
            'wilaya' => 'Tlemcen',
        ];
    }
}
