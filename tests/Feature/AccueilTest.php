<?php

namespace Tests\Feature;

use App\Enums\ModeAttributionRdv;
use App\Models\Patient;
use App\Models\RendezVous;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsPlanning;
use Tests\TestCase;

/**
 * Patients, écran de prise de RDV et ticket PDF (Étape 4).
 */
class AccueilTest extends TestCase
{
    use BuildsPlanning, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer();
    }

    public function test_ipp_is_generated_sequentially_with_configured_format(): void
    {
        $sec = $this->secretaire();
        $payload = ['nom' => 'Benali', 'prenom' => 'Sara', 'date_naissance' => '1990-05-01', 'sexe' => 'F'];

        $this->actingAs($sec)->post('/accueil/patients', $payload)->assertSessionHasNoErrors();
        $this->post('/accueil/patients', ['nom' => 'Autre', 'prenom' => 'Pers'])->assertSessionHasNoErrors();

        $this->assertSame(['IPP-2030-000001', 'IPP-2030-000002'], Patient::orderBy('id')->pluck('ipp')->all());

        config()->set('mawid.ipp_prefix', 'CHU');
        $this->post('/accueil/patients', ['nom' => 'Troisième', 'prenom' => 'X']);
        $this->assertSame('CHU-2030-000001', Patient::latest('id')->value('ipp'));
    }

    public function test_possible_duplicate_requires_confirmation(): void
    {
        $sec = $this->secretaire();
        $data = ['nom' => 'Benali', 'prenom' => 'Sara', 'date_naissance' => '1990-05-01'];

        $this->actingAs($sec)->post('/accueil/patients', $data);
        $this->post('/accueil/patients', $data)->assertSessionHasErrors('nom');
        $this->post('/accueil/patients', [...$data, 'confirmer_doublon' => true])->assertSessionHasNoErrors();

        $this->assertSame(2, Patient::count());
    }

    public function test_patient_search_matches_ipp_name_phone_and_nin(): void
    {
        $sec = $this->secretaire();
        $p = Patient::factory()->create(['nom' => 'Zerrouki', 'prenom' => 'Amine', 'telephone' => '0555123456', 'nin' => '109876543210']);
        Patient::factory()->create(['nom' => 'Autre']);

        foreach (['zerrou', 'Amine', '555123', '1098765', $p->ipp] as $terme) {
            $this->actingAs($sec)->get('/accueil/patients?q='.urlencode($terme))
                ->assertInertia(fn (Assert $page) => $page->has('patients.data', 1)->where('patients.data.0.id', $p->id));
        }
    }

    public function test_patients_cannot_be_deleted_only_edited(): void
    {
        $sec = $this->secretaire();
        $p = Patient::factory()->create();

        $this->actingAs($sec)->delete("/accueil/patients/{$p->id}")->assertStatus(405);
        $this->put("/accueil/patients/{$p->id}", ['nom' => 'Nouveau', 'prenom' => $p->prenom])->assertSessionHasNoErrors();
        $this->assertSame('Nouveau', $p->fresh()->nom);
    }

    public function test_only_reception_roles_can_book_or_edit(): void
    {
        $service = $this->service();
        $medecin = $this->medecin($service)->user;

        $this->actingAs($medecin)->get('/accueil/patients')->assertOk();
        $this->get('/accueil/rendez-vous/nouveau')->assertForbidden();
        $this->post('/accueil/patients', ['nom' => 'A', 'prenom' => 'B'])->assertForbidden();
    }

    public function test_booking_screen_only_offers_types_the_desk_may_book(): void
    {
        $service = $this->service(['mode_attribution_rdv' => ModeAttributionRdv::Hybride]);
        $this->types($service);
        $this->vacation($service, $this->medecin($service));

        $this->actingAs($this->secretaire())->get('/accueil/rendez-vous/nouveau')
            ->assertInertia(fn (Assert $p) => $p->has('services.0.types', 1)->where('services.0.types.0.code', 'premiere_consultation'));

        $this->actingAs($this->secretaire($service))->get('/accueil/rendez-vous/nouveau')
            ->assertInertia(fn (Assert $p) => $p->has('services.0.types', 2));
    }

    public function test_booking_flow_through_http_and_ticket_pdf(): void
    {
        $service = $this->service(['mode_attribution_rdv' => ModeAttributionRdv::Decentralise, 'documents_defaut_json' => ['ECG récent']]);
        $t = $this->types($service);
        $t['premiere']->update(['bilans_requis_text' => "Bilan sanguin\nRadio thorax"]);
        $v = $this->vacation($service, $this->medecin($service));
        $sec = $this->secretaire($service);
        $patient = Patient::factory()->create();
        $this->actingAs($sec);

        $this->get("/accueil/rendez-vous/nouveau?patient={$patient->id}&type={$t['premiere']->id}")
            ->assertInertia(fn (Assert $p) => $p->has('entries', 9)->where('entries.0.date', $this->mardi)->where('entries.0.remaining', 4));

        $this->postJson('/accueil/rendez-vous/verrou', ['type_consultation_id' => $t['premiere']->id, 'planning_id' => $v->id, 'date' => $this->mardi])
            ->assertOk()->assertJson(['ok' => true, 'ttl' => 60]);

        $this->post('/accueil/rendez-vous', [
            'patient_id' => $patient->id, 'type_consultation_id' => $t['premiere']->id, 'planning_id' => $v->id,
            'date' => $this->mardi, 'priorite' => 'normal',
        ])->assertSessionHasNoErrors();

        $rdv = RendezVous::firstOrFail();
        $this->assertSame('CAR-001', $rdv->numero_ticket);

        foreach (['80mm', 'a5'] as $format) {
            $r = $this->get("/accueil/rendez-vous/{$rdv->id}/ticket/$format")->assertOk();
            $this->assertSame('application/pdf', $r->headers->get('content-type'));
            $this->assertStringStartsWith('%PDF', $r->getContent());
        }
        $this->get("/accueil/rendez-vous/{$rdv->id}/ticket/a4")->assertNotFound();
    }

    public function test_lock_endpoint_returns_409_for_the_second_desk(): void
    {
        $service = $this->service(['mode_attribution_rdv' => ModeAttributionRdv::Decentralise]);
        $t = $this->types($service);
        $v = $this->vacation($service, $this->medecin($service), ['quota_rdv' => 1]);
        $payload = ['type_consultation_id' => $t['premiere']->id, 'planning_id' => $v->id, 'date' => $this->mardi];

        $this->actingAs($this->secretaire($service))->postJson('/accueil/rendez-vous/verrou', $payload)->assertOk();
        $this->actingAs($this->secretaire($service))->postJson('/accueil/rendez-vous/verrou', $payload)->assertStatus(409)->assertJson(['ok' => false]);
    }

    public function test_booking_error_is_returned_to_the_form(): void
    {
        $service = $this->service(['mode_attribution_rdv' => ModeAttributionRdv::Centralise]);
        $t = $this->types($service);
        $v = $this->vacation($service, $this->medecin($service));

        $this->actingAs($this->secretaire($service)) // secrétariat de service : interdit en mode centralisé
            ->post('/accueil/rendez-vous', [
                'patient_id' => Patient::factory()->create()->id, 'type_consultation_id' => $t['premiere']->id,
                'planning_id' => $v->id, 'date' => $this->mardi, 'priorite' => 'normal',
            ])->assertSessionHasErrors(['booking' => 'rdv.erreur.droits']);
    }
}
