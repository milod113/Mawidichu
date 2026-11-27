<?php

namespace App\Http\Controllers\Parametrage;

use App\Enums\ActionPlanning;
use App\Http\Controllers\Controller;
use App\Models\ExceptionPlanning;
use App\Models\Medecin;
use App\Models\Service;
use App\Services\PlanningDisruptionService;
use App\Services\QueueException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Absences et fermetures de planning (congé, garde, congrès…) avec basculade
 * des rendez-vous déjà pris.
 */
class ExceptionPlanningController extends Controller
{
    public function index(Request $request, PlanningDisruptionService $disruption): Response
    {
        $user = $request->user();
        $services = Service::where('is_active', true)->orderBy('nom')->get()->filter(fn (Service $s) => $user->canManageService($s))->values();

        $absences = ExceptionPlanning::query()
            ->whereIn('service_id', $services->modelKeys())
            ->with(['service:id,nom,nom_ar', 'medecin.user:id,name'])
            ->orderByDesc('date_debut')->limit(100)->get()
            ->map(fn (ExceptionPlanning $a) => [
                'id' => $a->id, 'service_id' => $a->service_id,
                'service' => $a->service->only(['nom', 'nom_ar']),
                'medecin' => $a->medecin?->user?->name,
                'medecin_id' => $a->medecin_id,
                'date_debut' => $a->date_debut->toDateString(), 'date_fin' => $a->date_fin->toDateString(),
                'motif' => $a->motif, 'action' => $a->action_appliquee?->value,
                'affectes' => $disruption->affectes($a)->count(),
            ]);

        return Inertia::render('Parametrage/Absences/Index', [
            'absences' => $absences,
            'services' => $services->map(fn (Service $s) => [
                'id' => $s->id, 'nom' => $s->nom, 'nom_ar' => $s->nom_ar,
                'medecins' => $s->medecins()->with('user:id,name')->get()->map(fn (Medecin $m) => ['id' => $m->id, 'name' => $m->user?->name]),
            ]),
            'actions' => ActionPlanning::values(),
        ]);
    }

    public function store(Request $request, PlanningDisruptionService $disruption): RedirectResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'medecin_id' => ['nullable', 'integer', Rule::exists('medecins', 'id')->whereNull('deleted_at')],
            'date_debut' => ['required', 'date_format:Y-m-d'],
            'date_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
            'motif' => ['required', 'string', 'max:255'],
            'action' => ['nullable', Rule::enum(ActionPlanning::class)],
            'confrere_id' => ['nullable', 'integer', 'exists:medecins,id'],
        ]);

        abort_unless($request->user()->canManageService((int) $data['service_id']), 403);
        if ($data['medecin_id'] && Medecin::find($data['medecin_id'])->service_id !== (int) $data['service_id']) {
            return back()->withErrors(['medecin_id' => 'absence.erreur.medecin']);
        }

        // Création et basculade forment un tout : si l'action est refusée, l'absence n'est pas créée.
        try {
            DB::transaction(function () use ($data, $disruption) {
                $absence = ExceptionPlanning::create([
                    'service_id' => $data['service_id'], 'medecin_id' => $data['medecin_id'] ?? null,
                    'date_debut' => $data['date_debut'], 'date_fin' => $data['date_fin'], 'motif' => $data['motif'],
                ]);
                if (! empty($data['action'])) {
                    $confrere = ! empty($data['confrere_id']) ? Medecin::find($data['confrere_id']) : null;
                    $disruption->appliquer($absence, ActionPlanning::from($data['action']), $confrere);
                }
            });
        } catch (QueueException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return back()->with('success', empty($data['action']) ? 'absence.created' : 'absence.bascule');
    }

    /**
     * Applique (ou réapplique) une action de basculade sur les RDV restants.
     */
    public function appliquer(Request $request, ExceptionPlanning $absence, PlanningDisruptionService $disruption): RedirectResponse
    {
        abort_unless($request->user()->canManageService($absence->service_id), 403);
        $data = $request->validate([
            'action' => ['required', Rule::enum(ActionPlanning::class)],
            'confrere_id' => ['nullable', 'integer', 'exists:medecins,id'],
        ]);

        return $this->basculer($absence, ActionPlanning::from($data['action']), $data['confrere_id'] ?? null, $disruption);
    }

    public function destroy(Request $request, ExceptionPlanning $absence): RedirectResponse
    {
        abort_unless($request->user()->canManageService($absence->service_id), 403);
        $absence->delete();

        return back()->with('success', 'absence.deleted');
    }

    private function basculer(ExceptionPlanning $absence, ActionPlanning $action, ?int $confrereId, PlanningDisruptionService $disruption): RedirectResponse
    {
        try {
            $disruption->appliquer($absence, $action, $confrereId ? Medecin::find($confrereId) : null);
        } catch (QueueException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return back()->with('success', 'absence.bascule');
    }
}
