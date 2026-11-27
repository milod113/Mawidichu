<?php

namespace App\Http\Controllers\Parametrage;

use App\Enums\Grade;
use App\Enums\RoleName;
use App\Enums\SurbookingUnite;
use App\Http\Controllers\Controller;
use App\Models\Medecin;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MedecinController extends Controller
{
    public function store(Request $request, Service $service): RedirectResponse
    {
        Gate::authorize('manage', $service);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            ...$this->rules(),
        ]);

        $user = User::findOrFail($data['user_id']);
        if (! $user->hasAnyRole([RoleName::Medecin->value, RoleName::ChefService->value])) {
            throw ValidationException::withMessages(['user_id' => __('validation.in', ['attribute' => __('validation.attributes.user_id')])]);
        }

        // Un profil médecin est unique par utilisateur (même supprimé) : on le réactive si besoin.
        $existing = Medecin::withTrashed()->where('user_id', $user->id)->first();
        if ($existing && ! $existing->trashed()) {
            throw ValidationException::withMessages(['user_id' => __('validation.unique', ['attribute' => __('validation.attributes.user_id')])]);
        }

        if ($existing) {
            $existing->restore();
            $existing->update([...$data, 'service_id' => $service->id]);
        } else {
            $service->medecins()->create($data);
        }

        return back()->with('success', 'medecin.created');
    }

    public function update(Request $request, Service $service, Medecin $medecin): RedirectResponse
    {
        Gate::authorize('manage', $service);
        abort_unless($medecin->service_id === $service->id, 404);

        $medecin->update($request->validate($this->rules()));

        return back()->with('success', 'medecin.updated');
    }

    public function destroy(Service $service, Medecin $medecin): RedirectResponse
    {
        Gate::authorize('manage', $service);
        abort_unless($medecin->service_id === $service->id, 404);

        $medecin->delete();

        return back()->with('success', 'medecin.deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'grade' => ['required', Rule::enum(Grade::class)],
            'specialite' => ['nullable', 'string', 'max:255'],
            'duree_consult_defaut' => ['nullable', 'integer', 'min:1', 'max:480'],
            'quota_max_defaut' => ['nullable', 'integer', 'min:1', 'max:500'],
            'tolere_surbooking' => ['required', 'boolean'],
            'valeur_surbooking' => ['nullable', 'integer', 'min:0', 'max:500', 'required_if:tolere_surbooking,true'],
            'surbooking_unite' => ['required', Rule::enum(SurbookingUnite::class)],
        ];
    }
}
