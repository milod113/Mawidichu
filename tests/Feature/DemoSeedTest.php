<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\RendezVous;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoPatientsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_runs_is_idempotent_and_covers_all_statuses(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(30, Patient::count());
        $this->assertSame(Patient::count(), Patient::distinct('ipp')->count('ipp'));
        $statuts = RendezVous::pluck('statut')->map->value->unique()->all();
        foreach (['planifie', 'arrive', 'en_preconsultation', 'pret_medecin', 'appele', 'en_consultation', 'termine', 'absent', 'reporte', 'en_attente_retour', 'a_replanifier'] as $s) {
            $this->assertContains($s, $statuts);
        }

        $n = Patient::count();
        $this->seed(DemoPatientsSeeder::class);
        $this->assertSame($n, Patient::count());
    }

    public function test_demo_seed_never_runs_in_production(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->app['env'] = 'production';
        (new DemoPatientsSeeder)->run();

        $this->assertSame(0, Patient::count());
    }
}
