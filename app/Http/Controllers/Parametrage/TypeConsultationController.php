<?php

namespace App\Http\Controllers\Parametrage;

use App\Enums\CodeConsultation;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\TypeConsultation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TypeConsultationController extends Controller
{
    public function store(Request $request, Service $service): RedirectResponse
    {
        Gate::authorize('manage', $service);

        $service->typesConsultation()->create($this->validated($request, $service));

        return back()->with('success', 'type.created');
    }

    public function update(Request $request, Service $service, TypeConsultation $typeConsultation): RedirectResponse
    {
        Gate::authorize('manage', $service);
        abort_unless($typeConsultation->service_id === $service->id, 404);

        $typeConsultation->update($this->validated($request, $service, $typeConsultation));

        return back()->with('success', 'type.updated');
    }

    public function destroy(Service $service, TypeConsultation $typeConsultation): RedirectResponse
    {
        Gate::authorize('manage', $service);
        abort_unless($typeConsultation->service_id === $service->id, 404);

        $typeConsultation->delete();

        return back()->with('success', 'type.deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Service $service, ?TypeConsultation $type = null): array
    {
        return $request->validate([
            'code' => ['required', Rule::enum(CodeConsultation::class),
                Rule::unique('types_consultation', 'code')->where('service_id', $service->id)->ignore($type?->id)],
            'libelle' => ['required', 'string', 'max:255'],
            'duree_minutes' => ['required', 'integer', 'min:1', 'max:480'],
            'couleur' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'bilans_requis_text' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
