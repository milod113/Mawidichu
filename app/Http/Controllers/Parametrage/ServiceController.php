<?php

namespace App\Http\Controllers\Parametrage;

use App\Enums\AffichageTvMode;
use App\Enums\CodeConsultation;
use App\Enums\Grade;
use App\Enums\ModeAttributionRdv;
use App\Enums\ModePlanification;
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
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Service::class);

        $user = $request->user();

        $services = Service::query()
            ->withCount(['medecins', 'boxes'])
            ->orderBy('nom')
            ->get()
            ->filter(fn (Service $s) => $user->canManageService($s))
            ->values();

        return Inertia::render('Parametrage/Services/Index', [
            'services' => $services,
            'canCreate' => $user->can('create', Service::class),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Service::class);

        return Inertia::render('Parametrage/Services/Create', ['options' => self::options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Service::class);

        $service = Service::create($this->validated($request, null, admin: true));

        return redirect()->route('parametrage.services.show', $service)->with('success', 'service.created');
    }

    public function show(Request $request, Service $service): Response
    {
        Gate::authorize('view', $service);

        $isAdmin = $request->user()->hasRole(RoleName::SuperAdmin->value);

        $candidates = User::query()
            ->role([RoleName::Medecin->value, RoleName::ChefService->value])
            ->where('is_active', true)
            ->whereDoesntHave('medecin')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Parametrage/Services/Show', [
            'service' => $service,
            'boxes' => $service->boxes()->orderBy('numero_box')->get(),
            'typesConsultation' => $service->typesConsultation()->orderBy('code')->get(),
            'medecins' => $service->medecins()->with('user:id,name,email')->get()
                ->sortBy(fn (Medecin $m) => $m->user?->name)->values(),
            'plannings' => $service->planningsTypes()->with(['medecin.user:id,name', 'box:id,numero_box,nom_affichage'])
                ->orderBy('jour_semaine')->orderBy('heure_debut')->get()
                ->map(fn ($p) => [
                    ...$p->toArray(),
                    'heure_debut' => substr($p->heure_debut, 0, 5),
                    'heure_fin' => substr($p->heure_fin, 0, 5),
                ])->values(),
            'candidates' => $candidates,
            'isAdmin' => $isAdmin,
            'options' => self::options(),
        ]);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        Gate::authorize('update', $service);

        $admin = $request->user()->hasRole(RoleName::SuperAdmin->value);
        $service->update($this->validated($request, $service, $admin));

        return back()->with('success', 'service.updated');
    }

    public function destroy(Service $service): RedirectResponse
    {
        Gate::authorize('delete', $service);

        $service->delete();

        return redirect()->route('parametrage.services.index')->with('success', 'service.deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Service $service, bool $admin): array
    {
        $rules = [
            'mode_planification' => ['required', Rule::enum(ModePlanification::class)],
            'mode_attribution_rdv' => ['required', Rule::enum(ModeAttributionRdv::class)],
            'preconsultation_active' => ['required', 'boolean'],
            'affichage_tv_mode' => ['required', Rule::enum(AffichageTvMode::class)],
            'nb_box' => ['required', 'integer', 'min:1', 'max:100'],
            'documents_defaut_json' => ['nullable', 'array'],
            'documents_defaut_json.*' => ['nullable', 'string', 'max:255'],
        ];

        if ($admin) {
            $rules += [
                'nom' => ['required', 'string', 'max:255'],
                'nom_ar' => ['nullable', 'string', 'max:255'],
                // Les codes supprimés (suppression douce) restent réservés par l'index unique.
                'code' => ['required', 'string', 'max:20', Rule::unique('services', 'code')->ignore($service?->id)],
                'is_active' => ['required', 'boolean'],
            ];
        }

        $data = $request->validate($rules);

        if (array_key_exists('documents_defaut_json', $data)) {
            $data['documents_defaut_json'] = array_values(array_filter(
                array_map(fn ($v) => trim((string) $v), $data['documents_defaut_json'] ?? []),
                fn ($v) => $v !== '',
            )) ?: null;
        }

        return $data;
    }

    /**
     * Listes de valeurs proposées par les formulaires (les libellés sont traduits côté front).
     *
     * @return array<string, list<string>>
     */
    public static function options(): array
    {
        return [
            'mode_planification' => ModePlanification::values(),
            'mode_attribution_rdv' => ModeAttributionRdv::values(),
            'affichage_tv_mode' => AffichageTvMode::values(),
            'grade' => Grade::values(),
            'surbooking_unite' => SurbookingUnite::values(),
            'code_consultation' => CodeConsultation::values(),
        ];
    }
}
