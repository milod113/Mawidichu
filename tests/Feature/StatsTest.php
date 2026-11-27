<?php

namespace Tests\Feature;

use App\Enums\CodeConsultation;
use App\Enums\Grade;
use App\Enums\RoleName;
use App\Enums\StatutRdv;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\TypeConsultation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPlanning;
use Tests\TestCase;

/**
 * Statistiques, périmètres et exports (Étape 7).
 */
class StatsTest extends TestCase
{
    use BuildsPlanning, RefreshDatabase;

    private Service $service;

    private Medecin $senior;

    private Medecin $resident;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer();
        $this->service = $this->service();
        $this->types($this->service);
        $this->senior = $this->medecin($this->service, ['grade' => Grade::MCA]);
        $this->resident = $this->medecin($this->service, ['grade' => Grade::Resident]);
    }

    private function rdv(Medecin $m, StatutRdv $statut, CodeConsultation $code = CodeConsultation::PremiereConsultation, int $attente = 0, string $date = '2030-01-05'): RendezVous
    {
        $type = TypeConsultation::where('service_id', $m->service_id)->where('code', $code->value)->first();
        $arrivee = \Illuminate\Support\Carbon::parse($date.' 09:00:00');

        return RendezVous::factory()->create([
            'service_id' => $m->service_id, 'medecin_id' => $m->id, 'type_consultation_id' => $type->id, 'date_rdv' => $date, 'statut' => $statut,
            'heure_arrivee_checkin' => $attente ? $arrivee : null, 'heure_appel' => $attente ? $arrivee->copy()->addMinutes($attente) : null,
        ]);
    }

    private function direction(): User
    {
        return User::factory()->create()->assignRole(RoleName::Direction->value);
    }

    public function test_kpis_volume_split_wait_and_workload(): void
    {
        $this->rdv($this->senior, StatutRdv::Termine, CodeConsultation::PremiereConsultation, 20);
        $this->rdv($this->senior, StatutRdv::Termine, CodeConsultation::Controle, 40);
        $this->rdv($this->resident, StatutRdv::Absent, CodeConsultation::RetourBilan);
        $this->rdv($this->resident, StatutRdv::AReplanifier);
        $this->rdv($this->senior, StatutRdv::Termine, CodeConsultation::PremiereConsultation, 0, '2029-06-01'); // hors période

        $r = $this->actingAs($this->direction())->get('/statistiques?du=2030-01-01&au=2030-01-07');

        $r->assertOk()->assertInertia(fn ($p) => $p
            ->where('stats.volume.prevus', 4)->where('stats.volume.realises', 2)->where('stats.volume.absents', 1)
            ->where('stats.volume.reportes', 1)->where('stats.volume.taux_absence', 25)
            ->where('stats.repartition.premiere_consultation', 2)->where('stats.repartition.controle', 1)->where('stats.repartition.retour_bilan', 1)
            ->where('stats.attente_moyenne', 30)
            ->where('stats.seniors_residents.seniors', 2)->where('stats.seniors_residents.residents', 2)
            ->has('stats.charge', 2)->has('stats.par_jour', 1));
    }

    public function test_filters_by_doctor_and_grade(): void
    {
        $this->rdv($this->senior, StatutRdv::Termine);
        $this->rdv($this->resident, StatutRdv::Termine);
        $this->actingAs($this->direction());

        $this->get("/statistiques?du=2030-01-01&au=2030-01-07&medecin_id={$this->resident->id}")->assertInertia(fn ($p) => $p->where('stats.volume.prevus', 1));
        $this->get('/statistiques?du=2030-01-01&au=2030-01-07&grade=MCA')->assertInertia(fn ($p) => $p->where('stats.volume.prevus', 1)->where('stats.charge.0.grade', 'MCA'));
        $this->get('/statistiques?du=2030-01-01&au=2030-01-07&grade=bidon')->assertInertia(fn ($p) => $p->where('stats.volume.prevus', 2));
    }

    public function test_service_head_is_limited_to_own_service(): void
    {
        $autre = Service::factory()->create(['code' => 'PED']);
        $this->types($autre);
        $this->rdv($this->senior, StatutRdv::Termine);
        $this->rdv($this->medecin($autre), StatutRdv::Termine);

        $chef = $this->senior->user->assignRole(RoleName::ChefService->value);
        $this->actingAs($chef)->get("/statistiques?du=2030-01-01&au=2030-01-07&service_id={$autre->id}")
            ->assertInertia(fn ($p) => $p->where('stats.volume.prevus', 1)->has('services', 1)->where('services.0.id', $this->service->id));

        $this->actingAs($this->direction())->get('/statistiques?du=2030-01-01&au=2030-01-07')->assertInertia(fn ($p) => $p->where('stats.volume.prevus', 2));
    }

    public function test_first_appointment_delay_uses_next_availability(): void
    {
        $this->vacation($this->service, $this->senior); // mardi 2030-01-08, aujourd'hui = lundi 2030-01-07

        $this->actingAs($this->direction())->get('/statistiques')->assertInertia(fn ($p) => $p
            ->where('stats.delais.0.date', '2030-01-08')->where('stats.delais.0.jours', 1));
    }

    public function test_exports_are_generated_and_restricted(): void
    {
        $this->rdv($this->senior, StatutRdv::Termine);
        $dir = $this->direction();

        $pdf = $this->actingAs($dir)->get('/statistiques/pdf?du=2030-01-01&au=2030-01-07')->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $xlsx = $this->get('/statistiques/excel?du=2030-01-01&au=2030-01-07')->assertOk();
        $this->assertStringContainsString('spreadsheetml', $xlsx->headers->get('content-type'));
        $this->assertSame('PK', substr(file_get_contents($xlsx->baseResponse->getFile()->getPathname()), 0, 2));

        $this->assertDatabaseHas('audit_logs', ['action' => 'export_stats_pdf']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'export_stats_excel']);

        $this->actingAs($this->secretaire())->get('/statistiques')->assertForbidden();
        $this->actingAs($this->resident->user)->get('/statistiques/pdf')->assertForbidden();
    }
}
