<?php

namespace Tests\Feature;

use App\Enums\Grade;
use App\Enums\RoleName;
use App\Enums\StatutRdv;
use App\Events\PatientCalled;
use App\Models\Consultation;
use App\Models\DemandeAvis;
use App\Models\DocumentPatient;
use App\Models\Medecin;
use App\Models\Patient;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\User;
use App\Services\ConsultationService;
use App\Services\QueueException;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsPlanning;
use Tests\TestCase;

/**
 * Appels, consultation, cloisonnement du dossier, avis, documents, PDF et écran TV (Étape 6).
 */
class ConsultationTest extends TestCase
{
    use BuildsPlanning, RefreshDatabase;

    private Service $service;

    private Medecin $doc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer();
        $this->service = $this->service(['aile' => 'Aile A']);
        $this->doc = $this->medecin($this->service, ['grade' => Grade::MCA]);
    }

    private function arrive(?Service $service = null, array $attrs = []): RendezVous
    {
        $service ??= $this->service;
        $type = $service->typesConsultation()->first() ?? $this->types($service)['premiere'];

        return RendezVous::factory()->create($attrs + [
            'service_id' => $service->id, 'type_consultation_id' => $type->id, 'date_rdv' => today()->toDateString(),
            'heure_vague_prevue' => '09:00:00', 'statut' => StatutRdv::Arrive, 'heure_arrivee_checkin' => now(),
        ]);
    }

    private function consultation(?RendezVous $rdv = null, ?Medecin $doc = null): Consultation
    {
        $doc ??= $this->doc;
        $rdv ??= $this->arrive($doc->service);
        $svc = app(ConsultationService::class);
        $svc->appeler($rdv, $doc);

        return $svc->demarrer($rdv->fresh(), $doc);
    }

    private function autreService(): array
    {
        $s = Service::factory()->create(['code' => 'PED']);
        $this->types($s);

        return [$s, $this->medecin($s)];
    }

    public function test_calling_follows_queue_order_and_broadcasts_to_tv(): void
    {
        Event::fake([PatientCalled::class]);
        $a = $this->arrive(null, ['numero_ticket' => 'A1', 'heure_arrivee_checkin' => now()->subMinutes(5)]);
        $b = $this->arrive(null, ['numero_ticket' => 'B1', 'heure_arrivee_checkin' => now()->subMinutes(20)]);

        $this->actingAs($this->doc->user)->post('/medecin/file/suivant')->assertSessionHasNoErrors();

        $this->assertSame(StatutRdv::Appele, $b->fresh()->statut);
        $this->assertSame(StatutRdv::Arrive, $a->fresh()->statut);
        $this->assertNotNull($b->fresh()->heure_appel);
        $this->assertSame($this->doc->id, $b->fresh()->medecin_id);
        Event::assertDispatched(PatientCalled::class, fn ($e) => $e->appel['ticket'] === 'B1' && $e->appel['nom'] === null);
    }

    public function test_with_preconsultation_only_ready_patients_can_be_called(): void
    {
        $this->service->update(['preconsultation_active' => true]);
        $this->doc->refresh();
        $rdv = $this->arrive();

        $this->expectException(QueueException::class);
        app(ConsultationService::class)->appeler($rdv, $this->doc->load('service'));
    }

    public function test_no_answer_puts_patient_on_return_wait_and_checkin_brings_them_back(): void
    {
        $rdv = $this->arrive();
        $svc = app(ConsultationService::class);
        $svc->appeler($rdv, $this->doc);
        $svc->absentALappel($rdv->fresh(), $this->doc);

        $this->assertSame(StatutRdv::EnAttenteRetour, $rdv->fresh()->statut);
        $this->assertSame(0, $rdv->patient->fresh()->nb_absences);

        app(QueueService::class)->checkIn($rdv->fresh());
        $this->assertSame(StatutRdv::Arrive, $rdv->fresh()->statut);
    }

    public function test_recall_refreshes_call_time(): void
    {
        $rdv = $this->arrive();
        $svc = app(ConsultationService::class);
        $svc->appeler($rdv, $this->doc);
        $this->travel(3)->minutes();
        $svc->rappeler($rdv->fresh(), $this->doc);

        $this->assertTrue($rdv->fresh()->heure_appel->gt(now()->subMinute()));
    }

    public function test_consultation_flow_saves_clinical_data_and_closes(): void
    {
        $c = $this->consultation();
        $user = $this->doc->user;

        $this->actingAs($user)->get("/medecin/consultations/{$c->id}")->assertOk();
        $this->put("/medecin/consultations/{$c->id}", [
            'diagnostic' => 'HTA', 'ordonnance' => [['medicament' => 'Amlodipine 5mg', 'posologie' => '1/j', 'duree' => '30 j']],
            'bilans' => ['NFS', 'HbA1c'], 'delai_controle_jours' => 30,
        ])->assertSessionHasNoErrors();
        $c->refresh();
        $this->assertSame('HTA', $c->diagnostic);
        $this->assertSame('Amlodipine 5mg', $c->ordonnance_json[0]['medicament']);
        $this->assertSame(StatutRdv::EnConsultation, $c->rendezVous->statut);

        $this->put("/medecin/consultations/{$c->id}", ['diagnostic' => 'HTA', 'cloturer' => true, 'delai_controle_jours' => 30])
            ->assertRedirect('/medecin/file');
        $this->assertSame(StatutRdv::Termine, $c->rendezVous->fresh()->statut);

        // Clôturée : plus modifiable.
        $this->put("/medecin/consultations/{$c->id}", ['diagnostic' => 'Autre'])->assertForbidden();
    }

    public function test_direct_control_booking_creates_follow_up_rdv(): void
    {
        $c = $this->consultation();
        $this->vacation($this->service, $this->doc, ['quota_rdv' => 4]);
        $planning = $this->service->planningsTypes()->first();

        $this->actingAs($this->doc->user)->put("/medecin/consultations/{$c->id}", [
            'cloturer' => true, 'delai_controle_jours' => 1,
            'controle_direct' => ['planning_id' => $planning->id, 'date' => $this->mardi],
        ])->assertSessionHasNoErrors();

        $c->refresh();
        $this->assertNotNull($c->controle_rdv_id);
        $controle = RendezVous::find($c->controle_rdv_id);
        $this->assertSame($this->mardi, $controle->date_rdv->toDateString());
        $this->assertSame('controle', $controle->typeConsultation->code->value);
    }

    public function test_assisted_control_shows_up_for_reception(): void
    {
        $c = $this->consultation();
        $this->actingAs($this->doc->user)->put("/medecin/consultations/{$c->id}", ['cloturer' => true, 'delai_controle_jours' => 14]);

        $this->actingAs($this->secretaire())->get('/accueil/jour?service='.$this->service->id)
            ->assertInertia(fn ($page) => $page->has('controles', 1)->where('controles.0.delai', 14));
    }

    public function test_dossier_is_cloistered_by_service(): void
    {
        $c = $this->consultation();
        [$autre, $docAutre] = $this->autreService();
        $dossier = "/dossier/patients/{$c->patient_id}";

        $this->actingAs($docAutre->user)->get("/medecin/consultations/{$c->id}")->assertForbidden();
        $this->get($dossier)->assertOk()->assertInertia(fn ($p) => $p->has('consultations', 0));
        $this->actingAs($this->doc->user)->get($dossier)->assertInertia(fn ($p) => $p->has('consultations', 1));

        $this->actingAs($this->secretaire())->get($dossier)->assertForbidden();
        $direction = User::factory()->create()->assignRole(RoleName::Direction->value);
        $this->actingAs($direction)->get($dossier)->assertForbidden();
    }

    public function test_inter_specialty_opinion_opens_dossier_temporarily(): void
    {
        $c = $this->consultation();
        [$autre, $docAutre] = $this->autreService();

        $this->actingAs($this->doc->user)->post("/medecin/consultations/{$c->id}/avis", [
            'type_avis' => 'inter_specialite', 'service_cible_id' => $autre->id, 'motif' => 'Avis cardio',
        ])->assertSessionHasNoErrors();
        $avis = DemandeAvis::first();
        $this->assertNotNull($avis->expire_le);

        $this->actingAs($docAutre->user)->get("/medecin/consultations/{$c->id}")->assertOk();
        $this->get("/dossier/patients/{$c->patient_id}")->assertInertia(fn ($p) => $p->has('consultations', 1));
        $this->put("/medecin/avis/{$avis->id}", ['reponse_avis' => 'RAS'])->assertSessionHasNoErrors();
        $this->assertSame('repondu', $avis->fresh()->statut->value);
        // lecture seule pour le service consulté
        $this->put("/medecin/consultations/{$c->id}", ['diagnostic' => 'x'])->assertForbidden();

        $this->travel(config('mawid.avis_validite_jours') + 1)->days();
        $this->get("/medecin/consultations/{$c->id}")->assertForbidden();
        $this->get("/dossier/patients/{$c->patient_id}")->assertInertia(fn ($p) => $p->has('consultations', 0));
    }

    public function test_senior_opinion_stays_in_service_and_records_supervisor(): void
    {
        $resident = $this->medecin($this->service, ['grade' => Grade::Resident]);
        $c = $this->consultation($this->arrive(), $resident);

        $this->actingAs($resident->user)->post("/medecin/consultations/{$c->id}/avis", ['type_avis' => 'senior_interne', 'medecin_cible_id' => $resident->id, 'motif' => 'm'])
            ->assertSessionHasErrors('medecin_cible_id');
        $this->post("/medecin/consultations/{$c->id}/avis", ['type_avis' => 'senior_interne', 'medecin_cible_id' => $this->doc->id, 'motif' => 'Confirmer le diagnostic'])
            ->assertSessionHasNoErrors();

        $avis = DemandeAvis::first();
        $this->assertNull($avis->expire_le);
        $this->actingAs($this->doc->user)->get('/medecin/avis')->assertInertia(fn ($p) => $p->has('recues', 1));
        $this->put("/medecin/avis/{$avis->id}", ['reponse_avis' => 'OK'])->assertSessionHasNoErrors();
        $this->assertSame($this->doc->id, $c->fresh()->superviseur_senior_id);
    }

    public function test_documents_are_stored_privately_and_authorized_by_service(): void
    {
        Storage::fake('local');
        $c = $this->consultation();
        [, $docAutre] = $this->autreService();
        $file = UploadedFile::fake()->create('lettre.pdf', 100, 'application/pdf');

        $this->actingAs($this->doc->user)->post("/dossier/patients/{$c->patient_id}/documents", [
            'service_id' => $this->service->id, 'type_document' => 'orientation', 'titre' => 'Lettre', 'fichier' => $file,
        ])->assertSessionHasNoErrors();
        $doc = DocumentPatient::first();
        Storage::disk('local')->assertExists($doc->fichier_path);

        $this->get("/dossier/documents/{$doc->id}")->assertOk();
        $this->actingAs($docAutre->user)->get("/dossier/documents/{$doc->id}")->assertForbidden();
        $this->post("/dossier/patients/{$c->patient_id}/documents", [
            'service_id' => $this->service->id, 'type_document' => 'autre', 'titre' => 'x', 'fichier' => $file,
        ])->assertForbidden();

        $this->actingAs($this->doc->user)->post("/dossier/patients/{$c->patient_id}/documents", [
            'service_id' => $this->service->id, 'type_document' => 'autre', 'titre' => 'x', 'fichier' => UploadedFile::fake()->create('virus.exe', 10),
        ])->assertSessionHasErrors('fichier');
    }

    public function test_pdfs_are_generated(): void
    {
        $c = $this->consultation();
        $c->update(['ordonnance_json' => [['medicament' => 'Doliprane', 'posologie' => '3/j', 'duree' => '5 j']], 'bilans_demandes_json' => ['NFS']]);
        $this->actingAs($this->doc->user);

        foreach (['ordonnance', 'bilan'] as $type) {
            $r = $this->get("/medecin/consultations/{$c->id}/pdf/{$type}")->assertOk();
            $this->assertStringStartsWith('%PDF', $r->getContent());
        }
        [$autre] = $this->autreService();
        $this->get("/medecin/consultations/{$c->id}/pdf/orientation?service_id={$autre->id}&motif=Avis")->assertOk();
        $this->get("/medecin/consultations/{$c->id}/pdf/inconnu")->assertNotFound();
        $this->get("/accueil/rendez-vous/{$c->rdv_id}/certificat")->assertOk();
    }

    public function test_favorite_medications_are_saved_per_doctor(): void
    {
        $this->actingAs($this->doc->user)->put('/medecin/favoris', ['favoris' => [['medicament' => 'Metformine 850', 'posologie' => '2/j', 'duree' => '90 j']]])
            ->assertSessionHasNoErrors();

        $this->assertSame('Metformine 850', $this->doc->fresh()->medicaments_favoris_json[0]['medicament']);
    }

    public function test_tv_shows_calls_by_wing_with_ticket_or_name(): void
    {
        $tv = User::factory()->create()->assignRole(RoleName::EcranTv->value);
        $rdv = $this->arrive(null, ['numero_ticket' => 'CAR-007']);
        app(ConsultationService::class)->appeler($rdv, $this->doc);
        [$autre] = $this->autreService();
        $autre->update(['aile' => 'Aile B']);
        app(ConsultationService::class)->appeler($this->arrive($autre, ['numero_ticket' => 'PED-001']), Medecin::where('service_id', $autre->id)->first());

        $this->actingAs($tv)->get('/dashboard')->assertRedirect('/tv');
        $this->get('/tv')->assertOk();
        $this->getJson('/tv/donnees?aile=Aile A')->assertJsonCount(1)->assertJsonPath('0.ticket', 'CAR-007')->assertJsonPath('0.nom', null);
        $this->getJson('/tv/donnees')->assertJsonCount(2);

        $this->service->update(['affichage_tv_mode' => 'nom']);
        $this->getJson('/tv/donnees?aile=Aile A')->assertJsonPath('0.nom', mb_strtoupper($rdv->patient->nom).' '.$rdv->patient->prenom);

        $this->actingAs($this->doc->user)->get('/tv')->assertForbidden();
    }
}
