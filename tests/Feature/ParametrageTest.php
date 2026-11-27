<?php

namespace Tests\Feature;

use App\Enums\Grade;
use App\Enums\RoleName;
use App\Models\Box;
use App\Models\JourFerie;
use App\Models\Medecin;
use App\Models\PlanningType;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Module de paramétrage (Étape 3) : droits, validations, jours fériés.
 */
class ParametrageTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(RoleName $role, string $email): User
    {
        return User::where('email', $email.'@'.config('mawid.seed_email_domain'))->firstOrFail();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return $this->userWith(RoleName::SuperAdmin, 'admin');
    }

    private function chef(): User
    {
        return $this->userWith(RoleName::ChefService, 'chef.service');
    }

    private function cardio(): Service
    {
        return Service::where('code', 'CARDIO')->firstOrFail();
    }

    public function test_only_admin_and_chef_can_open_parametrage(): void
    {
        $this->actingAs($this->userWith(RoleName::Secretaire, 'secretaire'))->get('/parametrage/services')->assertForbidden();
        $this->actingAs($this->userWith(RoleName::Medecin, 'medecin'))->get('/parametrage/services')->assertForbidden();
        $this->actingAs($this->admin())->get('/parametrage/services')->assertOk();
        $this->actingAs($this->chef())->get('/parametrage/services')->assertOk();
    }

    public function test_chef_only_sees_and_manages_own_service(): void
    {
        $pedia = Service::where('code', 'PEDIA')->firstOrFail();
        $this->actingAs($this->chef());

        $this->get('/parametrage/services')->assertInertia(fn (Assert $p) => $p
            ->has('services', 1)->where('services.0.code', 'CARDIO')->where('canCreate', false));

        $this->get("/parametrage/services/{$this->cardio()->id}")->assertOk();
        $this->get("/parametrage/services/{$pedia->id}")->assertForbidden();
        $this->post("/parametrage/services/{$pedia->id}/boxes", ['numero_box' => 5, 'is_active' => true])->assertForbidden();
        $this->get('/parametrage/services/create')->assertForbidden();
        $this->delete("/parametrage/services/{$this->cardio()->id}")->assertForbidden();
    }

    public function test_admin_creates_updates_and_soft_deletes_a_service(): void
    {
        $this->actingAs($this->admin());

        $this->post('/parametrage/services', [
            'nom' => 'Néphrologie', 'code' => 'NEPHRO', 'mode_planification' => 'mixte',
            'mode_attribution_rdv' => 'centralise', 'preconsultation_active' => false,
            'affichage_tv_mode' => 'nom', 'nb_box' => 3, 'is_active' => true,
            'documents_defaut_json' => ['Bilan rénal', '  ', 'Écho'],
        ])->assertRedirect();

        $service = Service::where('code', 'NEPHRO')->firstOrFail();
        $this->assertSame(['Bilan rénal', 'Écho'], $service->documents_defaut_json);
        $this->assertSame('mixte', $service->mode_planification->value);

        $this->put("/parametrage/services/{$service->id}", [
            'nom' => 'Néphrologie', 'code' => 'NEPHRO', 'mode_planification' => 'quota_vague',
            'mode_attribution_rdv' => 'hybride', 'preconsultation_active' => true,
            'affichage_tv_mode' => 'ticket', 'nb_box' => 4, 'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertSame(4, $service->fresh()->nb_box);

        $this->delete("/parametrage/services/{$service->id}");
        $this->assertSoftDeleted($service);
    }

    public function test_service_code_must_be_unique(): void
    {
        $this->actingAs($this->admin())->post('/parametrage/services', [
            'nom' => 'Doublon', 'code' => 'CARDIO', 'mode_planification' => 'mixte',
            'mode_attribution_rdv' => 'hybride', 'preconsultation_active' => false,
            'affichage_tv_mode' => 'ticket', 'nb_box' => 1, 'is_active' => true,
        ])->assertSessionHasErrors('code');
    }

    public function test_chef_cannot_change_identity_fields_of_the_service(): void
    {
        $service = $this->cardio();

        $this->actingAs($this->chef())->put("/parametrage/services/{$service->id}", [
            'nom' => 'Piraté', 'code' => 'HACK', 'is_active' => false,
            'mode_planification' => 'creneaux_dynamiques', 'mode_attribution_rdv' => 'decentralise',
            'preconsultation_active' => false, 'affichage_tv_mode' => 'nom', 'nb_box' => 5,
        ])->assertSessionHasNoErrors();

        $service->refresh();
        $this->assertSame('Cardiologie', $service->nom);
        $this->assertSame('CARDIO', $service->code);
        $this->assertTrue($service->is_active);
        $this->assertSame('creneaux_dynamiques', $service->mode_planification->value);
        $this->assertSame(5, $service->nb_box);
    }

    public function test_boxes_crud_with_unique_number_per_service(): void
    {
        $service = $this->cardio();
        $this->actingAs($this->chef());

        $this->post("/parametrage/services/{$service->id}/boxes", ['numero_box' => 3, 'nom_affichage' => 'Box 3', 'is_active' => true])
            ->assertSessionHasNoErrors();
        $this->post("/parametrage/services/{$service->id}/boxes", ['numero_box' => 3, 'is_active' => true])
            ->assertSessionHasErrors('numero_box');

        $box = Box::where('numero_box', 3)->firstOrFail();
        $this->put("/parametrage/services/{$service->id}/boxes/{$box->id}", ['numero_box' => 3, 'nom_affichage' => 'Renommé', 'is_active' => false])
            ->assertSessionHasNoErrors();
        $this->assertFalse($box->fresh()->is_active);

        $this->delete("/parametrage/services/{$service->id}/boxes/{$box->id}");
        $this->assertSoftDeleted($box);
    }

    public function test_cannot_touch_a_box_through_another_service(): void
    {
        $pedia = Service::where('code', 'PEDIA')->firstOrFail();
        $box = Box::firstOrFail();

        $this->actingAs($this->admin())
            ->delete("/parametrage/services/{$pedia->id}/boxes/{$box->id}")->assertNotFound();
    }

    public function test_consultation_types_are_configurable_per_service(): void
    {
        $pedia = Service::where('code', 'PEDIA')->firstOrFail();
        $this->actingAs($this->admin());

        $this->post("/parametrage/services/{$pedia->id}/types-consultation", [
            'code' => 'controle', 'libelle' => 'Contrôle pédiatrique', 'duree_minutes' => 7,
            'couleur' => '#112233', 'bilans_requis_text' => 'Carnet de santé', 'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertSame(7, $pedia->typesConsultation()->first()->duree_minutes);

        $this->post("/parametrage/services/{$pedia->id}/types-consultation", [
            'code' => 'controle', 'libelle' => 'Doublon', 'duree_minutes' => 5, 'is_active' => true,
        ])->assertSessionHasErrors('code');

        $this->post("/parametrage/services/{$pedia->id}/types-consultation", [
            'code' => 'retour_bilan', 'libelle' => 'x', 'duree_minutes' => 0, 'couleur' => 'rouge', 'is_active' => true,
        ])->assertSessionHasErrors(['duree_minutes', 'couleur']);
    }

    public function test_doctor_profile_is_attached_to_an_eligible_user_once(): void
    {
        $pedia = Service::where('code', 'PEDIA')->firstOrFail();
        $secretaire = $this->userWith(RoleName::Secretaire, 'secretaire');
        $this->actingAs($this->admin());

        $payload = ['grade' => 'Specialiste', 'tolere_surbooking' => true, 'valeur_surbooking' => 10, 'surbooking_unite' => 'pourcentage'];

        // Un utilisateur sans rôle médecin / chef de service n'est pas éligible.
        $this->post("/parametrage/services/{$pedia->id}/medecins", ['user_id' => $secretaire->id, ...$payload])
            ->assertSessionHasErrors('user_id');

        $medecinUser = User::factory()->create()->assignRole(RoleName::Medecin->value);
        $this->post("/parametrage/services/{$pedia->id}/medecins", ['user_id' => $medecinUser->id, ...$payload])
            ->assertSessionHasNoErrors();
        $profil = Medecin::where('user_id', $medecinUser->id)->firstOrFail();
        $this->assertSame(Grade::Specialiste, $profil->grade);

        $this->post("/parametrage/services/{$pedia->id}/medecins", ['user_id' => $medecinUser->id, ...$payload])
            ->assertSessionHasErrors('user_id');

        // Surbooking activé sans valeur : refusé.
        $this->put("/parametrage/services/{$pedia->id}/medecins/{$profil->id}", [...$payload, 'valeur_surbooking' => null])
            ->assertSessionHasErrors('valeur_surbooking');

        // Suppression douce puis réactivation du même profil.
        $this->delete("/parametrage/services/{$pedia->id}/medecins/{$profil->id}");
        $this->assertSoftDeleted($profil);
        $this->post("/parametrage/services/{$pedia->id}/medecins", ['user_id' => $medecinUser->id, ...$payload, 'grade' => 'MCA'])
            ->assertSessionHasNoErrors();
        $this->assertSame(Grade::MCA, $profil->fresh()->grade);
        $this->assertSame(1, Medecin::where('user_id', $medecinUser->id)->count());
    }

    public function test_planning_requires_exactly_one_assignment_and_mode_fields(): void
    {
        $service = $this->cardio();
        $medecin = $service->medecins()->first();
        $box = $service->boxes()->first();
        $this->actingAs($this->chef());
        $url = "/parametrage/services/{$service->id}/plannings";
        $avant = PlanningType::where('service_id', $service->id)->count();
        $base = ['jour_semaine' => 3, 'heure_debut' => '09:00', 'heure_fin' => '12:00', 'quota_rdv' => 15,
            'taille_vague' => 5, 'intervalle_vague_min' => 30, 'is_active' => true];

        $this->post($url, $base)->assertSessionHasErrors('medecin_id');
        $this->post($url, [...$base, 'medecin_id' => $medecin->id, 'box_id' => $box->id])->assertSessionHasErrors('medecin_id');
        $this->post($url, [...$base, 'medecin_id' => $medecin->id, 'heure_fin' => '08:00'])->assertSessionHasErrors('heure_fin');
        $this->post($url, [...$base, 'medecin_id' => $medecin->id, 'taille_vague' => null])->assertSessionHasErrors('taille_vague');

        // Mode dynamique : pas de quota ni de vague obligatoires.
        $this->post($url, [
            'box_id' => $box->id, 'jour_semaine' => 4, 'heure_debut' => '08:00', 'heure_fin' => '12:00',
            'mode_planification' => 'creneaux_dynamiques', 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->post($url, [...$base, 'medecin_id' => $medecin->id])->assertSessionHasNoErrors();
        $this->assertSame($avant + 2, PlanningType::where('service_id', $service->id)->count());
    }

    public function test_planning_cannot_use_a_doctor_from_another_service(): void
    {
        $pedia = Service::where('code', 'PEDIA')->firstOrFail();
        $cardioMedecin = $this->cardio()->medecins()->first();

        $this->actingAs($this->admin())->post("/parametrage/services/{$pedia->id}/plannings", [
            'medecin_id' => $cardioMedecin->id, 'jour_semaine' => 1, 'heure_debut' => '08:00', 'heure_fin' => '10:00',
            'quota_rdv' => 10, 'taille_vague' => 5, 'intervalle_vague_min' => 30, 'is_active' => true,
        ])->assertSessionHasErrors('medecin_id');
    }

    public function test_holidays_are_admin_only(): void
    {
        $this->actingAs($this->chef())->get('/parametrage/jours-feries')->assertForbidden();
        $this->actingAs($this->admin())->get('/parametrage/jours-feries?annee=2027')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Parametrage/JoursFeries/Index')->where('annee', 2027));
    }

    public function test_holiday_list_shows_recurrent_days_in_the_requested_year(): void
    {
        $this->actingAs($this->admin())->get('/parametrage/jours-feries?annee=2030')
            ->assertInertia(fn (Assert $p) => $p
                ->has('jours', 5) // les 5 fêtes fixes récurrentes, aucune fête lunaire seedée pour 2030
                ->where('jours.0.date_affichee', '2030-01-01'));
    }

    public function test_shifting_a_holiday_moves_the_whole_range_by_one_click(): void
    {
        $aid = JourFerie::where('libelle', 'like', 'Aïd el-Fitr%')->whereYear('date_ferie', 2027)->orderBy('date_ferie')->get();
        $this->assertCount(3, $aid);
        $this->assertNotNull($aid[0]->groupe);
        $this->assertSame(1, $aid->pluck('groupe')->unique()->count());

        $this->actingAs($this->admin())->post("/parametrage/jours-feries/{$aid[0]->id}/decaler", ['jours' => 1])
            ->assertSessionHasNoErrors();

        $apres = JourFerie::where('groupe', $aid[0]->groupe)->orderBy('date_ferie')->get();
        $this->assertSame(['2027-03-10', '2027-03-11', '2027-03-12'], $apres->map(fn ($j) => $j->date_ferie->toDateString())->all());

        $this->post("/parametrage/jours-feries/{$aid[0]->id}/decaler", ['jours' => -1]);
        $this->assertSame(
            ['2027-03-09', '2027-03-10', '2027-03-11'],
            JourFerie::where('groupe', $aid[0]->groupe)->orderBy('date_ferie')->get()->map(fn ($j) => $j->date_ferie->toDateString())->all(),
        );
    }

    public function test_confirming_a_holiday_confirms_all_its_days(): void
    {
        $premier = JourFerie::where('libelle', 'like', 'Aïd el-Adha%')->orderBy('date_ferie')->first();

        $this->actingAs($this->admin())->post("/parametrage/jours-feries/{$premier->id}/confirmer");

        $this->assertSame(0, JourFerie::where('groupe', $premier->groupe)->where('statut_confirmation', 'estimatif')->count());
    }

    public function test_shift_amount_is_validated(): void
    {
        $jour = JourFerie::firstOrFail();

        $this->actingAs($this->admin())->post("/parametrage/jours-feries/{$jour->id}/decaler", ['jours' => 0])
            ->assertSessionHasErrors('jours');
        $this->post("/parametrage/jours-feries/{$jour->id}/decaler", ['jours' => 30])->assertSessionHasErrors('jours');
    }

    public function test_holiday_crud_and_audit(): void
    {
        $this->actingAs($this->admin());

        $this->post('/parametrage/jours-feries', [
            'libelle' => 'Journée spéciale', 'libelle_ar' => null, 'date_ferie' => '2027-09-01',
            'type' => 'mobile', 'est_recurrent' => false, 'statut_confirmation' => 'estimatif',
        ])->assertSessionHasNoErrors();

        $jour = JourFerie::where('libelle', 'Journée spéciale')->firstOrFail();
        $this->put("/parametrage/jours-feries/{$jour->id}", [
            'libelle' => 'Journée spéciale', 'date_ferie' => '2027-09-02', 'type' => 'mobile',
            'est_recurrent' => false, 'statut_confirmation' => 'confirme',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2027-09-02', $jour->fresh()->date_ferie->toDateString());
        $this->assertTrue($jour->auditLogs()->where('action', 'updated')->exists());

        $this->delete("/parametrage/jours-feries/{$jour->id}");
        $this->assertSoftDeleted($jour);
    }

    public function test_navigation_exposes_parametrage_per_role(): void
    {
        $this->actingAs($this->chef())->get('/dashboard')->assertInertia(fn (Assert $p) => $p
            ->where('navigation', fn ($nav) => collect($nav)->pluck('items')->flatten(1)->pluck('route')->contains('parametrage.services.index')
                && ! collect($nav)->pluck('items')->flatten(1)->pluck('route')->contains('parametrage.jours-feries.index')));

        $this->actingAs($this->userWith(RoleName::Secretaire, 'secretaire'))->get('/dashboard')->assertInertia(fn (Assert $p) => $p
            ->where('navigation', fn ($nav) => ! collect($nav)->pluck('items')->flatten(1)->pluck('route')->contains('parametrage.services.index')));
    }
}
