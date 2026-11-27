<?php

namespace Tests\Feature;

use App\Enums\ModeAttributionRdv;
use App\Enums\ModePlanification;
use App\Enums\Priorite;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\ExceptionPlanning;
use App\Models\JourFerie;
use App\Models\Patient;
use App\Models\RendezVous;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingException;
use App\Services\BookingRules;
use App\Services\BookingService;
use App\Services\SlotLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPlanning;
use Tests\TestCase;

/**
 * Moteur de disponibilités, réservation, verrou et droits d'attribution (Étape 4).
 */
class AvailabilityTest extends TestCase
{
    use BuildsPlanning, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer();
    }

    private function book($user, $type, $planning, ?Priorite $p = null, ?string $heure = null, bool $surbooking = false, ?string $motif = null): RendezVous
    {
        return app(BookingService::class)->book(
            $user, Patient::factory()->create(), $type, $planning->id, $this->mardi,
            $p ?? Priorite::Normal, $motif, $heure, $surbooking,
        );
    }

    private function entry($service, $type, $medecin = null, ?Priorite $p = null): ?array
    {
        return app(AvailabilityService::class)->forService($service, $type, $this->mardi, $this->mardi, $medecin, $p)->first();
    }

    public function test_quota_vague_fills_waves_and_refuses_when_full(): void
    {
        $service = $this->service(['mode_attribution_rdv' => ModeAttributionRdv::Decentralise]);
        $t = $this->types($service);
        $v = $this->vacation($service, $this->medecin($service));
        $sec = $this->secretaire($service);

        $heures = collect(range(1, 4))->map(fn () => $this->book($sec, $t['premiere'], $v)->fresh()->heure_vague_prevue);
        $this->assertSame(['08:30:00', '08:30:00', '09:30:00', '09:30:00'], $heures->all());

        $e = $this->entry($service, $t['premiere']);
        $this->assertSame(0, $e['remaining']);
        $this->assertFalse($e['bookable']);

        $this->expectException(BookingException::class);
        $this->book($sec, $t['premiere'], $v);
    }

    public function test_surbooking_adds_capacity_only_when_requested(): void
    {
        $service = $this->service(['mode_attribution_rdv' => ModeAttributionRdv::Decentralise]);
        $t = $this->types($service);
        $medecin = $this->medecin($service, ['tolere_surbooking' => true, 'valeur_surbooking' => 25, 'surbooking_unite' => 'pourcentage']);
        $v = $this->vacation($service, $medecin); // quota 4 => +1 place à 25 %
        $sec = $this->secretaire($service);

        foreach (range(1, 4) as $i) {
            $this->book($sec, $t['premiere'], $v);
        }

        $e = $this->entry($service, $t['premiere']);
        $this->assertSame([0, 1, true], [$e['remaining'], $e['surbooking_remaining'], $e['bookable']]);

        try {
            $this->book($sec, $t['premiere'], $v);
            $this->fail('Doit refuser sans surbooking explicite');
        } catch (BookingException $e) {
            $this->assertSame('rdv.erreur.complet', $e->getMessage());
        }

        $this->book($sec, $t['premiere'], $v, surbooking: true);
        $this->assertSame(5, RendezVous::count());

        $this->expectException(BookingException::class);
        $this->book($sec, $t['premiere'], $v, surbooking: true);
    }

    public function test_dynamic_slots_use_the_duration_of_each_consultation_type(): void
    {
        $service = $this->service(['mode_planification' => ModePlanification::CreneauxDynamiques, 'mode_attribution_rdv' => ModeAttributionRdv::Decentralise]);
        $t = $this->types($service, premiere: 20, controle: 5);
        $v = $this->vacation($service, $this->medecin($service), ['heure_debut' => '08:00', 'heure_fin' => '09:00']);
        $sec = $this->secretaire($service);

        $this->assertCount(3, $this->entry($service, $t['premiere'])['slots']); // 08:00, 08:20, 08:40
        $this->assertCount(12, $this->entry($service, $t['controle'])['slots']);

        $this->book($sec, $t['premiere'], $v, heure: '08:00'); // occupe 08:00-08:20

        $slots = $this->entry($service, $t['controle'])['slots'];
        $this->assertSame('08:20', $slots[0]);
        $this->assertNotContains('08:10', $slots);

        // Le créneau pris n'est plus réservable.
        try {
            $this->book($sec, $t['controle'], $v, heure: '08:10');
            $this->fail('Créneau occupé');
        } catch (BookingException $e) {
            $this->assertSame('rdv.erreur.creneau', $e->getMessage());
        }

        $this->assertSame('08:20:00', $this->book($sec, $t['controle'], $v, heure: '08:20')->fresh()->heure_vague_prevue);
    }

    public function test_mixed_mode_keeps_a_separate_quota_for_same_day_returns(): void
    {
        $service = $this->service(['mode_planification' => ModePlanification::Mixte, 'mode_attribution_rdv' => ModeAttributionRdv::Decentralise]);
        $t = $this->types($service);
        $v = $this->vacation($service, $this->medecin($service), ['quota_rdv' => 1, 'quota_sans_rdv' => 2, 'taille_vague' => null, 'intervalle_vague_min' => null]);
        $sec = $this->secretaire($service);

        $this->book($sec, $t['premiere'], $v);
        $this->assertSame(0, $this->entry($service, $t['premiere'])['remaining']);
        $this->assertSame(2, $this->entry($service, $t['bilan'])['remaining']);

        $this->book($sec, $t['bilan'], $v);
        $this->book($sec, $t['controle'], $v, Priorite::OrientationInterServices, motif: 'Orienté par la cardiologie')
            ->priorite;
        $this->assertSame(0, $this->entry($service, $t['bilan'])['remaining']);

        $this->expectException(BookingException::class);
        $this->book($sec, $t['bilan'], $v);
    }

    public function test_holidays_and_absences_block_vacations(): void
    {
        $service = $this->service();
        $t = $this->types($service);
        $m1 = $this->medecin($service);
        $m2 = $this->medecin($service);
        $this->vacation($service, $m1);
        $this->vacation($service, $m2);

        $this->assertCount(2, app(AvailabilityService::class)->forService($service, $t['premiere'], $this->mardi, $this->mardi));

        // Absence d'un seul médecin.
        ExceptionPlanning::create(['service_id' => $service->id, 'medecin_id' => $m1->id, 'date_debut' => $this->mardi, 'date_fin' => $this->mardi, 'motif' => 'Congrès']);
        $this->assertSame([$m2->id], app(AvailabilityService::class)->forService($service, $t['premiere'], $this->mardi, $this->mardi)->pluck('medecin_id')->all());

        // Jour férié (même estimatif) : tout le service est bloqué.
        JourFerie::create(['libelle' => 'Férié test', 'date_ferie' => $this->mardi, 'type' => 'mobile', 'est_recurrent' => false, 'statut_confirmation' => 'estimatif']);
        $this->assertCount(0, app(AvailabilityService::class)->forService($service, $t['premiere'], $this->mardi, $this->mardi));
    }

    public function test_recurrent_fixed_holiday_blocks_future_years(): void
    {
        $service = $this->service();
        $t = $this->types($service);
        // 1er janvier 2031 est un mercredi (jour_semaine 3).
        $this->vacation($service, $this->medecin($service), ['jour_semaine' => 3]);
        JourFerie::create(['libelle' => 'Nouvel An', 'date_ferie' => '2026-01-01', 'type' => 'fixe', 'est_recurrent' => true, 'statut_confirmation' => 'confirme']);

        $this->assertCount(0, app(AvailabilityService::class)->forService($service, $t['premiere'], '2031-01-01', '2031-01-01'));
        $this->assertCount(1, app(AvailabilityService::class)->forService($service, $t['premiere'], '2031-01-08', '2031-01-08'));
    }

    public function test_past_dates_and_inactive_vacations_are_ignored(): void
    {
        $service = $this->service();
        $t = $this->types($service);
        $this->vacation($service, $this->medecin($service), ['is_active' => false]);

        $this->assertCount(0, app(AvailabilityService::class)->forService($service, $t['premiere'], '2029-12-01', '2030-01-31'));
    }

    public function test_attribution_rights_follow_the_service_mode(): void
    {
        $service = $this->service(['mode_attribution_rdv' => ModeAttributionRdv::Centralise]);
        $t = $this->types($service);
        $central = $this->secretaire();
        $locale = $this->secretaire($service);
        $autre = $this->secretaire($this->service(['code' => 'XYZ']));
        $rules = app(BookingRules::class);

        $this->assertTrue($rules->canBookType($central, $t['premiere']));
        $this->assertFalse($rules->canBookType($locale, $t['premiere']));

        $service->update(['mode_attribution_rdv' => ModeAttributionRdv::Decentralise]);
        $this->assertFalse($rules->canBookType($central, $t['premiere']->fresh('service')));
        $this->assertTrue($rules->canBookType($locale, $t['premiere']->fresh('service')));
        $this->assertFalse($rules->canBookType($autre, $t['premiere']->fresh('service')));

        $service->update(['mode_attribution_rdv' => ModeAttributionRdv::Hybride]);
        $premiere = $t['premiere']->fresh('service');
        $controle = $t['controle']->fresh('service');
        $this->assertTrue($rules->canBookType($central, $premiere));
        $this->assertFalse($rules->canBookType($locale, $premiere));
        $this->assertFalse($rules->canBookType($central, $controle));
        $this->assertTrue($rules->canBookType($locale, $controle));

        $medecin = $this->medecin($service)->user;
        $this->assertTrue($rules->canBookType($medecin, $controle));
        $this->assertFalse($rules->canBookType($medecin, $premiere));

        $admin = User::factory()->create()->assignRole(RoleName::SuperAdmin->value);
        $this->assertTrue($rules->canBookType($admin, $premiere));
    }

    public function test_temporary_lock_prevents_two_desks_from_taking_the_last_place(): void
    {
        $locks = app(SlotLockService::class);

        $this->assertTrue($locks->acquire(1, $this->mardi, null, userId: 10, available: 1));
        $this->assertTrue($locks->acquire(1, $this->mardi, null, userId: 10, available: 1), 'Le détenteur peut renouveler');
        $this->assertFalse($locks->acquire(1, $this->mardi, null, userId: 11, available: 1));

        $locks->release(1, $this->mardi, null, 10);
        $this->assertTrue($locks->acquire(1, $this->mardi, null, userId: 11, available: 1));

        // Deux places : deux guichets peuvent réserver, pas trois.
        $this->assertTrue($locks->acquire(2, $this->mardi, null, 1, 2));
        $this->assertTrue($locks->acquire(2, $this->mardi, null, 2, 2));
        $this->assertFalse($locks->acquire(2, $this->mardi, null, 3, 2));

        // Créneau : un seul détenteur.
        $this->assertTrue($locks->acquire(3, $this->mardi, '09:00', 1, 1));
        $this->assertFalse($locks->acquire(3, $this->mardi, '09:00', 2, 1));
        $this->assertTrue($locks->acquire(3, $this->mardi, '09:20', 2, 1));
    }

    public function test_lock_expires_after_the_configured_delay(): void
    {
        $locks = app(SlotLockService::class);
        $locks->acquire(5, $this->mardi, null, 1, 1);

        $this->travel(config('mawid.verrou_secondes') + 1)->seconds();

        $this->assertTrue($locks->acquire(5, $this->mardi, null, 2, 1));
    }

    public function test_booking_is_refused_while_another_desk_holds_the_last_place(): void
    {
        $service = $this->service(['mode_attribution_rdv' => ModeAttributionRdv::Decentralise]);
        $t = $this->types($service);
        $v = $this->vacation($service, $this->medecin($service), ['quota_rdv' => 1]);
        $a = $this->secretaire($service);
        $b = $this->secretaire($service);

        app(SlotLockService::class)->acquire($v->id, $this->mardi, null, $a->id, 1);

        try {
            $this->book($b, $t['premiere'], $v);
            $this->fail('B ne doit pas passer devant A');
        } catch (BookingException $e) {
            $this->assertSame('rdv.erreur.verrou', $e->getMessage());
        }

        $this->assertSame($a->id, $this->book($a, $t['premiere'], $v)->created_by);
    }

    public function test_tickets_are_numbered_per_service_and_day_and_duplicates_refused(): void
    {
        $service = $this->service(['mode_attribution_rdv' => ModeAttributionRdv::Decentralise]);
        $t = $this->types($service);
        $v = $this->vacation($service, $this->medecin($service));
        $sec = $this->secretaire($service);

        $this->assertSame('CAR-001', $this->book($sec, $t['premiere'], $v)->numero_ticket);
        $this->assertSame('CAR-002', $this->book($sec, $t['premiere'], $v)->numero_ticket);

        $patient = Patient::factory()->create();
        app(BookingService::class)->book($sec, $patient, $t['premiere'], $v->id, $this->mardi);
        $this->expectExceptionMessage('rdv.erreur.doublon');
        app(BookingService::class)->book($sec, $patient, $t['controle'], $v->id, $this->mardi);
    }

    public function test_priority_requires_a_reason_and_is_audited(): void
    {
        $service = $this->service(['mode_attribution_rdv' => ModeAttributionRdv::Decentralise]);
        $t = $this->types($service);
        $v = $this->vacation($service, $this->medecin($service));
        $sec = $this->secretaire($service);
        $this->actingAs($sec);

        try {
            $this->book($sec, $t['premiere'], $v, Priorite::PersonnelChu);
            $this->fail('Motif obligatoire');
        } catch (BookingException $e) {
            $this->assertSame('rdv.erreur.motif_priorite', $e->getMessage());
        }

        $rdv = $this->book($sec, $t['premiere'], $v, Priorite::PersonnelChu, motif: 'Agent du CHU');
        $log = AuditLog::where('action', 'priorite_appliquee')->where('auditable_id', $rdv->id)->firstOrFail();
        $this->assertSame(['priorite' => 'personnel_chu', 'motif' => 'Agent du CHU'], $log->new_values);
        $this->assertSame($sec->id, $log->user_id);
    }

    public function test_first_availability_for_a_specialty(): void
    {
        $service = $this->service();
        $t = $this->types($service);
        $this->vacation($service, $this->medecin($service));

        $this->assertSame($this->mardi, app(AvailabilityService::class)->premiereDisponibilite($service, $t['premiere'])['date']);
    }
}
