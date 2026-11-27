<?php

namespace App\Http\Controllers\Accueil;

use App\Enums\Priorite;
use App\Events\ServiceActivity;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PlanningType;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\TypeConsultation;
use App\Services\AvailabilityService;
use App\Services\BookingException;
use App\Services\BookingRules;
use App\Services\BookingService;
use App\Services\SlotLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RendezVousController extends Controller
{
    public function create(Request $request, AvailabilityService $availability, BookingRules $rules): Response
    {
        $user = $request->user();

        $patient = Patient::find($request->integer('patient'));
        $services = Service::where('is_active', true)->orderBy('nom')->with(['typesConsultation' => fn ($q) => $q->where('is_active', true)->orderBy('code')])->get()
            ->map(function (Service $s) use ($rules, $user) {
                $types = $s->typesConsultation->filter(fn (TypeConsultation $t) => $rules->canBook($user, $s, $t->code))->values();

                return $types->isEmpty() ? null : [
                    'id' => $s->id, 'nom' => $s->nom, 'nom_ar' => $s->nom_ar,
                    'medecins' => $s->medecins()->with('user:id,name')->get()->map(fn ($m) => ['id' => $m->id, 'name' => $m->user?->name, 'grade' => $m->grade->value]),
                    'types' => $types->map(fn ($t) => ['id' => $t->id, 'code' => $t->code->value, 'libelle' => $t->libelle, 'duree_minutes' => $t->duree_minutes, 'couleur' => $t->couleur, 'bilans' => $t->bilans_requis_text]),
                ];
            })->filter()->values();

        $type = $request->integer('type') ? TypeConsultation::with('service')->find($request->integer('type')) : null;
        $entries = [];

        if ($type && $services->contains(fn ($s) => $s['id'] === $type->service_id) && $rules->canBookType($user, $type)) {
            $medecin = $request->integer('medecin') ? $type->service->medecins()->find($request->integer('medecin')) : null;
            $priorite = Priorite::tryFrom((string) $request->query('priorite'));
            $jours = min(180, max(7, $request->integer('jours', 60)));
            $entries = $availability->forService($type->service, $type, today(), today()->addDays($jours), $medecin, $priorite)->all();
        }

        return Inertia::render('Accueil/Rendezvous/Create', [
            'patient' => $patient,
            'services' => $services,
            'selection' => [
                'type' => $type?->id, 'service' => $type?->service_id, 'medecin' => $request->integer('medecin') ?: null,
                'priorite' => $request->query('priorite', 'normal'),
            ],
            'entries' => $entries,
            'priorites' => Priorite::values(),
            'verrouSecondes' => config('mawid.verrou_secondes'),
        ]);
    }

    public function store(Request $request, BookingService $booking): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')->whereNull('deleted_at')],
            'type_consultation_id' => ['required', 'integer', Rule::exists('types_consultation', 'id')->whereNull('deleted_at')],
            'planning_id' => ['required', 'integer', 'exists:plannings_types,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'heure' => ['nullable', 'date_format:H:i'],
            'priorite' => ['required', Rule::enum(Priorite::class)],
            'motif_priorite' => ['nullable', 'string', 'max:500'],
            'surbooking' => ['sometimes', 'boolean'],
        ]);

        try {
            $rdv = $booking->book(
                $request->user(),
                Patient::findOrFail($data['patient_id']),
                TypeConsultation::with('service')->findOrFail($data['type_consultation_id']),
                $data['planning_id'], $data['date'], Priorite::from($data['priorite']),
                $data['motif_priorite'] ?? null, $data['heure'] ?? null, (bool) ($data['surbooking'] ?? false),
            );
        } catch (BookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('accueil.rendez-vous.show', $rdv)->with('success', 'rdv.created');
    }

    public function show(Request $request, RendezVous $rendezVous): Response
    {
        abort_unless($request->user()->canAccessService($rendezVous->service_id), 403);

        $rendezVous->load(['patient', 'service', 'typeConsultation', 'medecin.user:id,name', 'box']);

        return Inertia::render('Accueil/Rendezvous/Show', [
            'rdv' => [
                'id' => $rendezVous->id, 'numero_ticket' => $rendezVous->numero_ticket,
                'date_rdv' => $rendezVous->date_rdv->toDateString(),
                'heure' => $rendezVous->heure_vague_prevue ? substr($rendezVous->heure_vague_prevue, 0, 5) : null,
                'statut' => $rendezVous->statut->value, 'priorite' => $rendezVous->priorite->value,
                'patient' => $rendezVous->patient->only(['id', 'ipp', 'nom', 'prenom']),
                'service' => $rendezVous->service->only(['nom', 'nom_ar']),
                'type' => $rendezVous->typeConsultation->only(['libelle', 'bilans_requis_text']),
                'medecin' => $rendezVous->medecin?->user?->name,
                'box' => $rendezVous->box?->nom_affichage,
            ],
        ]);
    }

    public function destroy(Request $request, RendezVous $rendezVous): RedirectResponse
    {
        Gate::allowIf($request->user()->hasRole('super_admin') || $request->user()->canAccessService($rendezVous->service_id)
            && $request->user()->hasRole('secretaire'));

        $rendezVous->delete();
        ServiceActivity::dispatch($rendezVous->service_id, 'quota', $rendezVous->date_rdv->toDateString());

        return back()->with('success', 'rdv.deleted');
    }

    /**
     * Réserve temporairement (60 s) la place choisie.
     */
    public function verrou(Request $request, AvailabilityService $availability, SlotLockService $locks, BookingRules $rules): JsonResponse
    {
        $data = $request->validate([
            'type_consultation_id' => ['required', 'integer', 'exists:types_consultation,id'],
            'planning_id' => ['required', 'integer', 'exists:plannings_types,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'heure' => ['nullable', 'date_format:H:i'],
            'priorite' => ['nullable', Rule::enum(Priorite::class)],
        ]);

        $type = TypeConsultation::with('service')->findOrFail($data['type_consultation_id']);
        abort_unless($rules->canBookType($request->user(), $type), 403);

        $planning = PlanningType::where('service_id', $type->service_id)->findOrFail($data['planning_id']);
        $entry = $availability->forService($type->service, $type, $data['date'], $data['date'], $planning->medecin,
            Priorite::tryFrom((string) ($data['priorite'] ?? '')))->firstWhere('planning_id', $planning->id);

        $disponible = $entry ? ($entry['slots'] !== null ? (int) in_array($data['heure'] ?? null, $entry['slots'], true)
            : $entry['remaining'] + $entry['surbooking_remaining']) : 0;

        $ok = $disponible > 0 && $locks->acquire($planning->id, $data['date'], $entry['slots'] !== null ? $data['heure'] : null, $request->user()->id, $disponible);

        return response()->json(['ok' => $ok, 'ttl' => $locks->ttl()], $ok ? 200 : 409);
    }

    public function liberer(Request $request, SlotLockService $locks): JsonResponse
    {
        $data = $request->validate([
            'planning_id' => ['required', 'integer'], 'date' => ['required', 'date_format:Y-m-d'], 'heure' => ['nullable', 'date_format:H:i'],
        ]);
        $locks->release($data['planning_id'], $data['date'], $data['heure'] ?? null, $request->user()->id);
        // Le créneau dynamique est libéré par sa clé "heure", le quota par la clé commune.
        $locks->release($data['planning_id'], $data['date'], null, $request->user()->id);

        return response()->json(['ok' => true]);
    }
}
