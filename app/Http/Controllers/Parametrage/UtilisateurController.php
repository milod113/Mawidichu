<?php

namespace App\Http\Controllers\Parametrage;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Comptes utilisateurs (super administrateur) : création, rôles, affectation au service,
 * activation / désactivation, réinitialisation du mot de passe, suppression douce.
 * Garde-fous : on ne peut ni se désactiver / supprimer soi-même, ni retirer le dernier
 * super administrateur actif.
 */
class UtilisateurController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $role = $request->query('role');
        $statut = $request->query('statut');

        $users = User::query()->with(['roles:id,name', 'affectationService:id,nom', 'medecin:id,user_id,service_id,grade'])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('telephone', 'like', $like));
            })
            ->when(in_array($role, RoleName::values(), true), fn ($query) => $query->role($role))
            ->when($statut === 'actif', fn ($query) => $query->where('is_active', true))
            ->when($statut === 'inactif', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')->paginate(15)->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'telephone' => $u->telephone, 'is_active' => $u->is_active,
                'locale' => $u->locale, 'roles' => $u->roles->pluck('name'), 'affectation_service_id' => $u->affectation_service_id,
                'affectation' => $u->affectationService?->nom, 'service_medecin' => $u->medecin?->service_id,
                'moi' => $u->id === $request->user()->id,
            ]);

        return Inertia::render('Parametrage/Utilisateurs/Index', [
            'users' => $users,
            'filters' => ['q' => $q, 'role' => $role, 'statut' => $statut],
            'roles' => RoleName::values(),
            'services' => Service::where('is_active', true)->orderBy('nom')->get(['id', 'nom']),
            'locales' => array_keys(config('mawid.locales')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            ...$this->rules(),
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', Password::min(8)],
        ]);

        $user = User::create([
            ...collect($data)->except(['roles', 'password'])->all(),
            'password' => Hash::make($data['password']), 'email_verified_at' => now(),
        ]);
        $user->syncRoles($data['roles']);
        AuditLog::record('compte_cree', $user, null, ['roles' => $data['roles']]);

        return back()->with('success', 'user.created');
    }

    public function update(Request $request, User $utilisateur): RedirectResponse
    {
        $data = $request->validate([
            ...$this->rules(),
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($utilisateur->id)],
            'password' => ['nullable', Password::min(8)],
        ]);

        $roles = $data['roles'];
        $perdSuper = $utilisateur->hasRole(RoleName::SuperAdmin->value) && ! in_array(RoleName::SuperAdmin->value, $roles, true);
        $desactive = ! $data['is_active'] && $utilisateur->is_active;

        if ($utilisateur->id === $request->user()->id && ($perdSuper || ! $data['is_active'])) {
            return back()->withErrors(['roles' => 'user.erreur.soi_meme']);
        }
        if (($perdSuper || ($desactive && $utilisateur->hasRole(RoleName::SuperAdmin->value))) && $this->dernierSuperAdmin($utilisateur)) {
            return back()->withErrors(['roles' => 'user.erreur.dernier_admin']);
        }

        $avant = ['roles' => $utilisateur->getRoleNames()->all(), 'is_active' => $utilisateur->is_active];
        $utilisateur->fill(collect($data)->except(['roles', 'password'])->all());
        if (! empty($data['password'])) {
            $utilisateur->password = Hash::make($data['password']);
        }
        $utilisateur->save();
        $utilisateur->syncRoles($roles);
        AuditLog::record('compte_modifie', $utilisateur, $avant, ['roles' => $roles, 'is_active' => $utilisateur->is_active, 'mot_de_passe_change' => ! empty($data['password'])]);

        if ($desactive || ! empty($data['password'])) {
            $this->fermerSessions($utilisateur);
        }

        return back()->with('success', 'user.updated');
    }

    public function destroy(Request $request, User $utilisateur): RedirectResponse
    {
        if ($utilisateur->id === $request->user()->id) {
            return back()->withErrors(['roles' => 'user.erreur.soi_meme']);
        }
        if ($utilisateur->hasRole(RoleName::SuperAdmin->value) && $this->dernierSuperAdmin($utilisateur)) {
            return back()->withErrors(['roles' => 'user.erreur.dernier_admin']);
        }

        $utilisateur->update(['is_active' => false]);
        $utilisateur->delete(); // suppression douce : l'historique des RDV et consultations reste attribué
        $this->fermerSessions($utilisateur);

        return back()->with('success', 'user.deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', Rule::enum(RoleName::class)],
            'affectation_service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'is_active' => ['required', 'boolean'],
            'locale' => ['required', Rule::in(array_keys(config('mawid.locales')))],
        ];
    }

    private function dernierSuperAdmin(User $user): bool
    {
        return User::role(RoleName::SuperAdmin->value)->where('is_active', true)->where('id', '!=', $user->id)->doesntExist();
    }

    private function fermerSessions(User $user): void
    {
        if (config('session.driver') === 'database' && Schema::hasTable(config('session.table', 'sessions'))) {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
