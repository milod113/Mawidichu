<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de référence (tous environnements) puis comptes de test
     * (uniquement en local / testing).
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            JourFerieSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call([TestUserSeeder::class, DemoDataSeeder::class]);
        }
    }
}
