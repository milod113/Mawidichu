<?php

namespace App\Http\Controllers\Parametrage;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Models\Medecin;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BoxController extends Controller
{
    public function store(Request $request, Service $service): RedirectResponse
    {
        Gate::authorize('manage', $service);

        $service->boxes()->create($this->validated($request, $service));

        return back()->with('success', 'box.created');
    }

    public function update(Request $request, Service $service, Box $box): RedirectResponse
    {
        Gate::authorize('manage', $service);
        abort_unless($box->service_id === $service->id, 404);

        $box->update($this->validated($request, $service, $box));

        return back()->with('success', 'box.updated');
    }

    public function destroy(Service $service, Box $box): RedirectResponse
    {
        Gate::authorize('manage', $service);
        abort_unless($box->service_id === $service->id, 404);

        $box->delete();

        return back()->with('success', 'box.deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Service $service, ?Box $box = null): array
    {
        return $request->validate([
            // Un numéro supprimé (suppression douce) reste réservé : préférer désactiver le box.
            'numero_box' => ['required', 'integer', 'min:1', 'max:999',
                Rule::unique('boxes', 'numero_box')->where('service_id', $service->id)->ignore($box?->id)],
            'nom_affichage' => ['nullable', 'string', 'max:255'],
            'medecin_actuel_id' => ['nullable', 'integer',
                Rule::exists(Medecin::class, 'id')->where('service_id', $service->id)->whereNull('deleted_at')],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
