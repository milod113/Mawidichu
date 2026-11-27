<?php

namespace Tests\Feature;

use App\Enums\ActionPlanning;
use App\Enums\Priorite;
use App\Enums\RoleName;
use App\Enums\StatutRdv;
use App\Models\AuditLog;
use App\Models\ExceptionPlanning;
use App\Models\Patient;
use App\Models\RendezVous;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PlanningDisruptionService;
use App\Services\QueueException;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPlanning;
use Tests\TestCase;

/**
 * File du jour, pré-consultation et basculade des absences (Étape 5).
 */
class FileDuJourTest extends TestCase
{
    use BuildsPlanning, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer();
    }

    private function rdv(array $attrs = []): RendezVous
    {
        $service = $attrs['service'] ?? \App\Models\Service::firstWhere('code', 'CAR') ?? $this->service();
        unset($attrs['service']);
        $type = $service->typesConsultation()->first() ?? $this->types($service)['premiere'];

        return RendezVous::factory()->create($attrs + [
            'service_id' => $service->id, 'type_consultation_id' => $type->id,
            'date_rdv' => today()->toDateString(), 'heure_vague_prevue' => '09:00:00',
        ]);
    }

    public function test_checkin_only_on_the_day_and_records_arrival_time(): void
    {
        $queue = app(QueueService::class);
        $demain = $this->rdv(['date_rdv' => today()->addDay()->toDateString()]);
        try {
            $queue->checkIn($demain);
            $this->fail('Pointage anticipé accepté');
        } catch (QueueException $e) {
            $this->assertSame('file.erreur.pas_aujourdhui', $e->getMessage());
        }

        $rdv = $this->rdv();
        $queue->checkIn($rdv);
        $this->assertSame(StatutRdv::Arrive, $rdv->fresh()->statut);
        $this->assertNotNull($rdv->fresh()->heure_arrivee_checkin);

        $this->expectException(QueueException::class);
        $queue->checkIn($rdv->fresh()); // déjà arrivé
    }

    public function test_priority_requires_a_reason_and_is_audited(): void
    {
        $queue = app(QueueService::class);
        $rdv = $this->rdv();

        try {
            $queue->appliquerPriorite($rdv, Priorite::HorsWilaya, ' ');
            $this->fail();
        } catch (QueueException $e) {
            $this->assertSame('rdv.erreur.motif_priorite', $e->getMessage());
        }

        $queue->appliquerPriorite($rdv, Priorite::HorsWilaya, 'Vient de Béchar');
        $this->assertSame(Priorite::HorsWilaya, $rdv->fresh()->priorite);
        $log = AuditLog::where('action', 'priorite_appliquee')->latest('id')->first();
        $this->assertSame('normal', $log->old_values['priorite']);
        $this->assertSame('hors_wilaya', $log->new_values['priorite']);
    }

    public function test_absent_increments_patient_counter(): void
    {
        $rdv = $this->rdv();
        app(QueueService::class)->marquerAbsent($rdv);

        $this->assertSame(StatutRdv::Absent, $rdv->fresh()->statut);
        $this->assertSame(1, $rdv->patient->fresh()->nb_absences);
    }

    public function test_queue_order_uses_configured_rank_then_checkin_time(): void
    {
        $queue = app(QueueService::class);
        $a = $this->rdv(['numero_ticket' => 'A', 'statut' => StatutRdv::Arrive, 'heure_arrivee_checkin' => now()->subMinutes(30)]);
        $b = $this->rdv(['numero_ticket' => 'B', 'statut' => StatutRdv::Arrive, 'heure_arrivee_checkin' => now()->subMinutes(20), 'priorite' => Priorite::PersonneAgeeEnceinte]);
        $c = $this->rdv(['numero_ticket' => 'C', 'statut' => StatutRdv::Arrive, 'heure_arrivee_checkin' => now()->subMinutes(10)]);

        // Par défaut, aucune priorité absolue : ordre d'arrivée.
        $this->assertSame(['A', 'B', 'C'], $queue->ordonner(collect([$c, $b, $a]))->pluck('numero_ticket')->all());

        config()->set('mawid.queue_priority_rank.personne_agee_enceinte', 5);
        $this->assertSame(['B', 'A', 'C'], $queue->ordonner(collect([$c, $b, $a]))->pluck('numero_ticket')->all());
    }

    public function test_preconsultation_flow_stores_vitals_and_makes_patient_ready(): void
    {
        $service = $this->service(['preconsultation_active' => true]);
        $rdv = $this->rdv(['service' => $service, 'statut' => StatutRdv::Arrive, 'heure_arrivee_checkin' => now()]);
        $inf = User::factory()->create(['affectation_service_id' => $service->id])->assignRole(RoleName::Infirmier->value);

        $this->actingAs($inf)->post("/infirmier/preconsultation/{$rdv->id}/demarrer")->assertSessionHasNoErrors();
        $this->assertSame(StatutRdv::EnPreconsultation, $rdv->fresh()->statut);

        $this->post("/infirmier/preconsultation/{$rdv->id}/constantes", ['tension_sys' => 130, 'tension_dia' => 80, 'poids' => 72.5])
            ->assertSessionHasNoErrors();
        $this->assertSame(StatutRdv::PretMedecin, $rdv->fresh()->statut);
        $this->assertSame($inf->id, $rdv->constantes->infirmier_id);
        $this->assertSame(130, $rdv->constantes->tension_sys);

        $this->post("/infirmier/preconsultation/{$rdv->id}/constantes", ['tension_sys' => 5])->assertSessionHasErrors('tension_sys');
        $this->get('/infirmier/preconsultation')->assertOk();
    }

    public function test_preconsultation_refused_when_service_does_not_use_it(): void
    {
        $service = $this->service(['preconsultation_active' => false]);
        $rdv = $this->rdv(['service' => $service, 'statut' => StatutRdv::Arrive]);

        $this->expectException(QueueException::class);
        app(QueueService::class)->demarrerPreconsultation($rdv);
    }

    public function test_nurse_of_another_service_cannot_touch_the_rdv(): void
    {
        $service = $this->service(['preconsultation_active' => true]);
        $autre = \App\Models\Service::factory()->create(['code' => 'PED', 'preconsultation_active' => true]);
        $rdv = $this->rdv(['service' => $service, 'statut' => StatutRdv::Arrive]);
        $inf = User::factory()->create(['affectation_service_id' => $autre->id])->assignRole(RoleName::Infirmier->value);

        $this->actingAs($inf)->post("/infirmier/preconsultation/{$rdv->id}/demarrer")->assertForbidden();
    }

    public function test_reception_endpoints_and_page(): void
    {
        $rdv = $this->rdv();
        $sec = $this->secretaire();

        $this->actingAs($sec)->get('/accueil/jour')->assertOk();
        $this->post("/accueil/rendez-vous/{$rdv->id}/arrivee")->assertSessionHasNoErrors();
        $this->post("/accueil/rendez-vous/{$rdv->id}/priorite", ['priorite' => 'personnel_chu'])->assertSessionHasErrors('file');
        $this->post("/accueil/rendez-vous/{$rdv->id}/priorite", ['priorite' => 'personnel_chu', 'motif_priorite' => 'Agent'])->assertSessionHasNoErrors();
        $this->assertSame(Priorite::PersonnelChu, $rdv->fresh()->priorite);

        $inf = User::factory()->create()->assignRole(RoleName::Infirmier->value);
        $this->actingAs($inf)->post("/accueil/rendez-vous/{$rdv->id}/absent")->assertForbidden();
    }

    public function test_ticket_numbers_stay_unique_per_service_and_day(): void
    {
        $service = $this->service();
        $types = $this->types($service);
        $med = $this->medecin($service);
        $this->vacation($service, $med, ['quota_rdv' => 6]);
        $sec = $this->secretaire();

        $numeros = [];
        foreach (range(1, 4) as $i) {
            $numeros[] = app(BookingService::class)->book($sec, Patient::factory()->create(), $types['premiere'],
                $service->planningsTypes()->first()->id, $this->mardi)->numero_ticket;
        }

        $this->assertSame(['CAR-001', 'CAR-002', 'CAR-003', 'CAR-004'], $numeros);
    }

    /**
     * @return array{0: \App\Models\Service, 1: \App\Models\Medecin, 2: \App\Models\Medecin, 3: array}
     */
    private function cabinet(): array
    {
        $service = $this->service();
        $types = $this->types($service);
        $a = $this->medecin($service);
        $b = $this->medecin($service);
        $this->vacation($service, $a, ['quota_rdv' => 4]);
        $this->vacation($service, $b, ['quota_rdv' => 4]);

        return [$service, $a, $b, $types];
    }

    private function reserver($service, $types, $medecin, int $n): void
    {
        $planning = $service->planningsTypes()->where('medecin_id', $medecin->id)->first();
        foreach (range(1, $n) as $_) {
            app(BookingService::class)->book($this->secretaire(), Patient::factory()->create(), $types['premiere'], $planning->id, $this->mardi);
        }
    }

    public function test_absence_blocks_slots_and_reaffects_to_a_colleague(): void
    {
        [$service, $a, $b, $types] = $this->cabinet();
        $this->reserver($service, $types, $a, 2);

        $absence = ExceptionPlanning::create(['service_id' => $service->id, 'medecin_id' => $a->id, 'date_debut' => $this->mardi, 'date_fin' => $this->mardi, 'motif' => 'Congé']);
        $bilan = app(PlanningDisruptionService::class)->appliquer($absence, ActionPlanning::ReaffecterConfrere, $b);

        $this->assertSame(2, $bilan['reaffectes']);
        $this->assertSame(2, RendezVous::where('medecin_id', $b->id)->count());
        $this->assertSame(0, RendezVous::where('medecin_id', $a->id)->count());
        $this->assertSame(ActionPlanning::ReaffecterConfrere, $absence->fresh()->action_appliquee);
    }

    public function test_reaffect_overflow_goes_to_replanifier_instead_of_overbooking(): void
    {
        [$service, $a, $b, $types] = $this->cabinet();
        $this->reserver($service, $types, $a, 3);
        $this->reserver($service, $types, $b, 3); // il ne reste qu'une place chez le confrère

        $absence = ExceptionPlanning::create(['service_id' => $service->id, 'medecin_id' => $a->id, 'date_debut' => $this->mardi, 'date_fin' => $this->mardi, 'motif' => 'Garde']);
        $bilan = app(PlanningDisruptionService::class)->appliquer($absence, ActionPlanning::ReaffecterConfrere, $b);

        $this->assertSame(1, $bilan['reaffectes']);
        $this->assertSame(2, $bilan['a_replanifier']);
        $this->assertSame(2, RendezVous::where('statut', StatutRdv::AReplanifier)->count());
        $this->assertSame(0, RendezVous::where('statut', StatutRdv::Planifie)->where('medecin_id', $a->id)->count());
    }

    public function test_reporting_moves_rdv_to_next_vacation_with_new_ticket(): void
    {
        [$service, $a, , $types] = $this->cabinet();
        $this->reserver($service, $types, $a, 2);

        $absence = ExceptionPlanning::create(['service_id' => $service->id, 'medecin_id' => $a->id, 'date_debut' => $this->mardi, 'date_fin' => $this->mardi, 'motif' => 'Congrès']);
        $bilan = app(PlanningDisruptionService::class)->appliquer($absence, ActionPlanning::ReporterProchaineVacation);

        $this->assertSame(2, $bilan['reportes']);
        $this->assertSame(['2030-01-15'], RendezVous::where('medecin_id', $a->id)->pluck('date_rdv')->map->toDateString()->unique()->values()->all());
        $this->assertCount(2, RendezVous::where('medecin_id', $a->id)->pluck('numero_ticket')->unique());
    }

    public function test_absence_endpoints_are_restricted_and_validated(): void
    {
        [$service, $a, $b] = $this->cabinet();
        $admin = User::factory()->create()->assignRole(RoleName::SuperAdmin->value);

        $this->actingAs($admin)->get('/parametrage/absences')->assertOk();
        $this->post('/parametrage/absences', ['service_id' => $service->id, 'medecin_id' => $a->id, 'date_debut' => $this->mardi, 'date_fin' => '2030-01-01', 'motif' => 'x'])
            ->assertSessionHasErrors('date_fin');
        $this->post('/parametrage/absences', ['service_id' => $service->id, 'medecin_id' => $a->id, 'date_debut' => $this->mardi, 'date_fin' => $this->mardi, 'motif' => 'Congé', 'action' => 'reaffecter_confrere'])
            ->assertSessionHasErrors('action');
        $this->post('/parametrage/absences', ['service_id' => $service->id, 'medecin_id' => $a->id, 'date_debut' => $this->mardi, 'date_fin' => $this->mardi, 'motif' => 'Congé', 'action' => 'reaffecter_confrere', 'confrere_id' => $b->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, ExceptionPlanning::count());

        $this->actingAs($this->secretaire())->get('/parametrage/absences')->assertForbidden();
    }
}
