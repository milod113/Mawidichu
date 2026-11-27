<?php

namespace App\Http\Controllers\Parametrage;

use App\Enums\ModePlanification;
use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Models\Medecin;
use App\Models\PlanningType;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlanningTypeController extends Controller
{
    public function store(Request $request, Service $service): RedirectResponse
    {
        Gate::authorize('manage', $service);

        $service->planningsTypes()->create($this->validated($request, $service));

        return back()->with('success', 'planning.created');
    }

    public function update(Request $request, Service $service, PlanningType $planningType): RedirectResponse
    {
        Gate::authorize('manage', $service);
        abort_unless($planningType->service_id === $service->id, 404);

        $planningType->update($this->validated($request, $service));

        return back()->with('success', 'planning.updated');
    }

    public function destroy(Service $service, PlanningType $planningType): RedirectResponse
    {
        Gate::authorize('manage', $service);
        abort_unless($planningType->service_id === $service->id, 404);

        $planningType->delete();

        return back()->with('success', 'planning.deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Service $service): array
    {
        $data = $request->validate([
            'medecin_id' => ['nullable', 'integer',
                Rule::exists(Medecin::class, 'id')->where('service_id', $service->id)->whereNull('deleted_at')],
            'box_id' => ['nullable', 'integer',
                Rule::exists(Box::class, 'id')->where('service_id', $service->id)->whereNull('deleted_at')],
            'jour_semaine' => ['required', 'integer', 'between:0,6'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
            'mode_planification' => ['nullable', Rule::enum(ModePlanification::class)],
            'quota_rdv' => ['nullable', 'integer', 'min:0', 'max:500'],
            'quota_sans_rdv' => ['nullable', 'integer', 'min:0', 'max:500'],
            'taille_vague' => ['nullable', 'integer', 'min:1', 'max:500'],
            'intervalle_vague_min' => ['nullable', 'integer', 'min:1', 'max:480'],
            'is_active' => ['required', 'boolean'],
        ]);

        // Affectation nominative OU par box / équipe, jamais les deux ni aucune.
        if (empty($data['medecin_id']) === empty($data['box_id'])) {
            throw ValidationException::withMessages(['medecin_id' => __('parametrage.planning.affectation_required')]);
        }

        // Le mode effectif (vacation, sinon service) conditionne les champs obligatoires.
        $mode = $data['mode_planification'] ?? $service->mode_planification->value;

        if ($mode !== ModePlanification::CreneauxDynamiques->value && ! isset($data['quota_rdv'])) {
            throw ValidationException::withMessages(['quota_rdv' => __('validation.required', ['attribute' => __('validation.attributes.quota_rdv')])]);
        }

        if ($mode === ModePlanification::QuotaVague->value) {
            foreach (['taille_vague', 'intervalle_vague_min'] as $field) {
                if (! isset($data[$field])) {
                    throw ValidationException::withMessages([$field => __('validation.required', ['attribute' => __('validation.attributes.'.$field)])]);
                }
            }
        }

        return $data;
    }
}
