<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Vérifications du socle technique (Étape 1).
 */
class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_the_seven_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertEqualsCanonicalizing(
            RoleName::values(),
            Role::pluck('name')->all(),
        );
        $this->assertCount(7, RoleName::cases());
    }

    public function test_seeders_create_one_test_account_per_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (RoleName::cases() as $role) {
            $this->assertTrue(
                User::role($role->value)->exists(),
                "Aucun compte de test pour le rôle {$role->value}",
            );
        }

        $chef = User::where('email', 'chef.service@'.config('mawid.seed_email_domain'))->firstOrFail();
        $this->assertTrue($chef->hasAllRoles([RoleName::ChefService->value, RoleName::Medecin->value]));
    }

    public function test_seeders_are_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $counts = [DB::table('jours_feries')->count(), User::count(), Role::count()];

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts, [DB::table('jours_feries')->count(), User::count(), Role::count()]);
    }

    public function test_algerian_public_holidays_are_seeded(): void
    {
        $this->seed(DatabaseSeeder::class);

        $fixes = DB::table('jours_feries')->where('type', 'fixe')->get();
        $this->assertCount(5, $fixes);
        $this->assertTrue($fixes->every(fn ($j) => (bool) $j->est_recurrent && $j->statut_confirmation === 'confirme'));
        $this->assertEqualsCanonicalizing(
            ['01-01', '01-12', '05-01', '07-05', '11-01'],
            $fixes->map(fn ($j) => substr($j->date_ferie, 5, 5))->all(),
        );

        $mobiles = DB::table('jours_feries')->where('type', 'mobile');
        $this->assertSame(0, (clone $mobiles)->where('statut_confirmation', '!=', 'estimatif')->count());
        // Par année : Aïd el-Fitr 3 j + Aïd el-Adha 3 j + Awal Muharram + Achoura + Mawlid = 9 jours.
        $this->assertSame(9, (clone $mobiles)->whereYear('date_ferie', 2027)->count());
    }

    public function test_inactive_users_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_locale_can_be_switched_to_arabic_with_rtl(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/locale', ['locale' => 'ar'])->assertRedirect();

        $this->assertSame('ar', $user->fresh()->locale);

        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('locale', 'ar')
                ->where('dir', 'rtl')
                ->where('translations', fn ($t) => $t['nav.dashboard'] === 'لوحة القيادة'));
    }

    public function test_unsupported_default_locale_falls_back_to_a_supported_one(): void
    {
        config()->set('app.locale', 'en');

        $this->get('/login')->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'fr')
            ->where('translations', fn ($t) => $t['auth.login.title'] === 'Connexion'));
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $this->post('/locale', ['locale' => 'xx'])->assertSessionHasErrors('locale');
    }

    public function test_guest_locale_is_kept_in_session(): void
    {
        $this->post('/locale', ['locale' => 'ar']);

        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('dir', 'rtl'));
    }

    public function test_navigation_only_lists_existing_routes_allowed_for_the_user(): void
    {
        config()->set('navigation', [[
            'section' => 'nav.section.general',
            'items' => [
                ['route' => 'dashboard', 'label' => 'nav.dashboard', 'icon' => 'home', 'roles' => null],
                ['route' => 'route.inexistante', 'label' => 'x', 'icon' => 'dot', 'roles' => null],
                ['route' => 'profile.edit', 'label' => 'nav.profile', 'icon' => 'user', 'roles' => [RoleName::SuperAdmin->value]],
            ],
        ]]);

        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(RoleName::Secretaire->value);

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->has('navigation', 1)
            ->has('navigation.0.items', 1)
            ->where('navigation.0.items.0.route', 'dashboard'));
    }

    public function test_super_admin_passes_every_gate(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create()->assignRole(RoleName::SuperAdmin->value);
        $other = User::factory()->create()->assignRole(RoleName::Direction->value);

        $this->assertTrue($admin->can('permission-quelconque'));
        $this->assertFalse($other->can('permission-quelconque'));
    }

    public function test_users_are_soft_deleted(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $this->assertSoftDeleted($user);
    }
}
