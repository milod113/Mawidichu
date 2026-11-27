<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Gestion des comptes utilisateurs par le super administrateur.
 */
class UtilisateurTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->admin = User::factory()->create()->assignRole(RoleName::SuperAdmin->value);
    }

    private function payload(array $over = []): array
    {
        return $over + ['name' => 'Samia Benali', 'email' => 'samia@mawid.test', 'telephone' => '0555000000', 'roles' => ['secretaire'],
            'affectation_service_id' => null, 'is_active' => true, 'locale' => 'fr', 'password' => 'MotDePasse-2030'];
    }

    public function test_only_super_admin_can_manage_accounts(): void
    {
        $this->actingAs(User::factory()->create()->assignRole(RoleName::ChefService->value))->get('/parametrage/utilisateurs')->assertForbidden();
        $this->actingAs($this->admin)->get('/parametrage/utilisateurs')->assertOk()->assertInertia(fn ($p) => $p->has('users.data', 2));
    }

    public function test_account_creation_assigns_roles_and_allows_login(): void
    {
        $service = Service::factory()->create();
        $this->actingAs($this->admin)->post('/parametrage/utilisateurs', $this->payload(['roles' => ['secretaire', 'infirmier'], 'affectation_service_id' => $service->id]))
            ->assertSessionHasNoErrors();

        $u = User::where('email', 'samia@mawid.test')->first();
        $this->assertTrue($u->hasAllRoles(['secretaire', 'infirmier']));
        $this->assertSame($service->id, $u->affectation_service_id);
        $this->assertTrue(Hash::check('MotDePasse-2030', $u->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'compte_cree', 'auditable_id' => $u->id]);

        auth()->logout();
        $this->post('/login', ['email' => 'samia@mawid.test', 'password' => 'MotDePasse-2030'])->assertRedirect('/dashboard');
    }

    public function test_validation_rejects_duplicate_email_weak_password_and_unknown_role(): void
    {
        $this->actingAs($this->admin);
        $this->post('/parametrage/utilisateurs', $this->payload(['email' => $this->admin->email]))->assertSessionHasErrors('email');
        $this->post('/parametrage/utilisateurs', $this->payload(['password' => 'court']))->assertSessionHasErrors('password');
        $this->post('/parametrage/utilisateurs', $this->payload(['roles' => ['hacker']]))->assertSessionHasErrors('roles.0');
        $this->post('/parametrage/utilisateurs', $this->payload(['roles' => []]))->assertSessionHasErrors('roles');
    }

    public function test_update_changes_roles_and_optionally_password(): void
    {
        $u = User::factory()->create(['password' => Hash::make('ancien-mdp-123')])->assignRole(RoleName::Secretaire->value);
        $this->actingAs($this->admin);

        $this->put("/parametrage/utilisateurs/{$u->id}", $this->payload(['email' => $u->email, 'roles' => ['medecin'], 'password' => '']))->assertSessionHasNoErrors();
        $this->assertTrue($u->fresh()->hasRole('medecin'));
        $this->assertFalse($u->fresh()->hasRole('secretaire'));
        $this->assertTrue(Hash::check('ancien-mdp-123', $u->fresh()->password));

        $this->put("/parametrage/utilisateurs/{$u->id}", $this->payload(['email' => $u->email, 'roles' => ['medecin']]))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('MotDePasse-2030', $u->fresh()->password));
    }

    public function test_deactivated_account_cannot_log_in(): void
    {
        $u = User::factory()->create(['password' => Hash::make('secret-1234')])->assignRole(RoleName::Secretaire->value);
        $this->actingAs($this->admin)->put("/parametrage/utilisateurs/{$u->id}", $this->payload(['email' => $u->email, 'is_active' => false, 'password' => '']))->assertSessionHasNoErrors();

        auth()->logout();
        $this->post('/login', ['email' => $u->email, 'password' => 'secret-1234'])->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_safeguards_protect_last_super_admin_and_self(): void
    {
        $this->actingAs($this->admin);

        $this->put("/parametrage/utilisateurs/{$this->admin->id}", $this->payload(['email' => $this->admin->email, 'roles' => ['medecin'], 'password' => '']))->assertSessionHasErrors('roles');
        $this->put("/parametrage/utilisateurs/{$this->admin->id}", $this->payload(['email' => $this->admin->email, 'roles' => ['super_admin'], 'is_active' => false, 'password' => '']))->assertSessionHasErrors('roles');
        $this->delete("/parametrage/utilisateurs/{$this->admin->id}")->assertSessionHasErrors('roles');
        $this->assertTrue($this->admin->fresh()->hasRole('super_admin'));

        // Un autre super admin existe : on peut en désactiver un, mais plus le dernier restant.
        $autre = User::factory()->create()->assignRole(RoleName::SuperAdmin->value);
        $this->delete("/parametrage/utilisateurs/{$autre->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted($autre);

        $dernier = User::factory()->create()->assignRole(RoleName::SuperAdmin->value);
        $this->actingAs($dernier)->delete("/parametrage/utilisateurs/{$this->admin->id}")->assertSessionHasNoErrors();
        $this->delete("/parametrage/utilisateurs/{$dernier->id}")->assertSessionHasErrors('roles');
    }

    public function test_soft_delete_keeps_the_record_and_filters_work(): void
    {
        $u = User::factory()->create(['name' => 'Zoubir Test'])->assignRole(RoleName::Infirmier->value);
        $this->actingAs($this->admin)->delete("/parametrage/utilisateurs/{$u->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted($u);

        User::factory()->create(['name' => 'Karim Médecin'])->assignRole(RoleName::Medecin->value);
        $this->get('/parametrage/utilisateurs?role=medecin')->assertInertia(fn ($p) => $p->has('users.data', 1)->where('users.data.0.name', 'Karim Médecin'));
        $this->get('/parametrage/utilisateurs?q=karim')->assertInertia(fn ($p) => $p->has('users.data', 1));
        // Le compte supprimé reste masqué ; un compte désactivé apparaît dans le filtre « inactif ».
        $this->get('/parametrage/utilisateurs?statut=inactif')->assertInertia(fn ($p) => $p->has('users.data', 0));
        User::factory()->inactive()->create();
        $this->get('/parametrage/utilisateurs?statut=inactif')->assertInertia(fn ($p) => $p->has('users.data', 1));
    }
}
