<?php

namespace App\Http\Controllers\Infirmier;

use App\Http\Controllers\Controller;
use App\Models\RendezVous;
use App\Models\Service;
use App\Services\QueueException;
use App\Services\QueueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pré-consultation (service qui l'active) : prise des constantes par l'infirmier
 * avant que le patient ne soit visible comme « prêt » par le médecin.
 */
class PreconsultationController extends Controller
{
    public function index(Request $request, QueueService $queue): Response
    {
        $user = $request->user();
        $services = Service::where('is_active', true)->where('preconsultation_active', true)->orderBy('nom')->get()
            ->filter(fn (Service $s) => $user->canAccessService($s))->values();
        $service = $services->firstWhere('id', $request->integer('service')) ?? $services->first();

        $rdvs = collect();
        if ($service) {
            $rdvs = $queue->ordonner(RendezVous::where('service_id', $service->id)->whereDate('date_rdv', today())
                ->whereIn('statut', QueueService::EN_SALLE)
                ->with(['patient:id,nom,prenom,ipp,date_naissance', 'typeConsultation:id,libelle', 'constantes'])->get())
                ->map(fn (RendezVous $r) => [
                    'id' => $r->id, 'numero_ticket' => $r->numero_ticket, 'statut' => $r->statut->value,
                    'priorite' => $r->priorite->value, 'checkin' => $r->heure_arrivee_checkin?->format('H:i'),
                    'patient' => $r->patient->only(['nom', 'prenom', 'ipp']),
                    'type' => $r->typeConsultation->libelle,
                    'constantes' => $r->constantes?->only(['tension_sys', 'tension_dia', 'frequence_cardiaque', 'poids', 'taille', 'temperature', 'glycemie', 'notes_ecg_soins']),
                ])->values();
        }

        return Inertia::render('Infirmier/Preconsultation', [
            'services' => $services->map(fn (Service $s) => $s->only(['id', 'nom', 'nom_ar'])),
            'service' => $service?->id,
            'rdvs' => $rdvs,
        ]);
    }

    public function demarrer(Request $request, RendezVous $rendezVous, QueueService $queue): RedirectResponse
    {
        abort_unless($request->user()->canAccessService($rendezVous->service_id), 403);

        try {
            $queue->demarrerPreconsultation($rendezVous);
        } catch (QueueException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('success', 'file.preconsultation_started');
    }

    public function store(Request $request, RendezVous $rendezVous, QueueService $queue): RedirectResponse
    {
        abort_unless($request->user()->canAccessService($rendezVous->service_id), 403);

        $data = $request->validate([
            'tension_sys' => ['nullable', 'integer', 'between:30,300'],
            'tension_dia' => ['nullable', 'integer', 'between:20,200'],
            'frequence_cardiaque' => ['nullable', 'integer', 'between:20,300'],
            'poids' => ['nullable', 'numeric', 'between:0.3,500'],
            'taille' => ['nullable', 'numeric', 'between:20,260'],
            'temperature' => ['nullable', 'numeric', 'between:30,45'],
            'glycemie' => ['nullable', 'numeric', 'between:0.1,20'],
            'notes_ecg_soins' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $queue->terminerPreconsultation($rendezVous, $request->user(), $data);
        } catch (QueueException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('success', 'file.preconsultation_done');
    }
}
