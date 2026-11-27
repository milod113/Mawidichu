<?php

namespace App\Http\Controllers\Accueil;

use App\Enums\Priorite;
use App\Enums\StatutRdv;
use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\RendezVous;
use App\Models\Service;
use App\Services\QueueException;
use App\Services\QueueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écran du jour de l'accueil : pointage des arrivées, badges de priorité, absents,
 * et rappel des patients à replanifier / des contrôles à programmer.
 */
class FileController extends Controller
{
    public function index(Request $request, QueueService $queue): Response
    {
        $user = $request->user();
        $services = Service::where('is_active', true)->orderBy('nom')->get()->filter(fn (Service $s) => $user->canAccessService($s))->values();
        $service = $services->firstWhere('id', $request->integer('service')) ?? $services->first();

        $rdvs = collect();
        $replanifier = collect();
        $controles = collect();

        if ($service) {
            $jour = RendezVous::where('service_id', $service->id)->whereDate('date_rdv', today())
                ->with(['patient:id,nom,prenom,ipp,nb_absences', 'typeConsultation:id,libelle,couleur', 'medecin.user:id,name'])->get();

            $presents = $queue->ordonner($jour->filter(fn (RendezVous $r) => in_array($r->statut, QueueService::EN_SALLE, true)));
            $autres = $jour->reject(fn (RendezVous $r) => in_array($r->statut, QueueService::EN_SALLE, true))
                ->sortBy([['heure_vague_prevue', 'asc'], ['numero_ticket', 'asc']]);
            $rdvs = $presents->concat($autres)->values()->map(fn (RendezVous $r, int $i) => $this->ligne($r, $i < $presents->count() ? $i + 1 : null));

            $replanifier = RendezVous::where('service_id', $service->id)->where('statut', StatutRdv::AReplanifier)
                ->with(['patient:id,nom,prenom,ipp', 'typeConsultation:id,libelle'])->orderBy('date_rdv')->get()
                ->map(fn (RendezVous $r) => [
                    'id' => $r->id, 'patient_id' => $r->patient_id, 'patient' => $r->patient->only(['nom', 'prenom', 'ipp']),
                    'type_id' => $r->type_consultation_id, 'type' => $r->typeConsultation->libelle, 'date_rdv' => $r->date_rdv->toDateString(),
                ]);

            $controles = Consultation::where('service_id', $service->id)->whereNotNull('delai_controle_jours')->whereNull('controle_rdv_id')
                ->with(['patient:id,nom,prenom,ipp', 'rendezVous:id,date_rdv'])->latest()->limit(50)->get()
                ->map(fn (Consultation $c) => [
                    'id' => $c->id, 'patient_id' => $c->patient_id, 'patient' => $c->patient->only(['nom', 'prenom', 'ipp']),
                    'delai' => $c->delai_controle_jours,
                    'echeance' => $c->rendezVous->date_rdv->copy()->addDays($c->delai_controle_jours)->toDateString(),
                ]);
        }

        return Inertia::render('Accueil/Jour', [
            'services' => $services->map(fn (Service $s) => $s->only(['id', 'nom', 'nom_ar', 'preconsultation_active'])),
            'service' => $service?->id,
            'date' => today()->toDateString(),
            'rdvs' => $rdvs,
            'replanifier' => $replanifier,
            'controles' => $controles,
            'priorites' => Priorite::values(),
            'peutAgir' => $user->hasAnyRole(['secretaire', 'super_admin', 'chef_service']),
        ]);
    }

    public function checkIn(Request $request, RendezVous $rendezVous, QueueService $queue): RedirectResponse
    {
        return $this->agir($request, $rendezVous, fn () => $queue->checkIn($rendezVous), 'file.checkin');
    }

    public function absent(Request $request, RendezVous $rendezVous, QueueService $queue): RedirectResponse
    {
        return $this->agir($request, $rendezVous, fn () => $queue->marquerAbsent($rendezVous), 'file.absent');
    }

    public function priorite(Request $request, RendezVous $rendezVous, QueueService $queue): RedirectResponse
    {
        $data = $request->validate([
            'priorite' => ['required', Rule::enum(Priorite::class)],
            'motif_priorite' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->agir($request, $rendezVous,
            fn () => $queue->appliquerPriorite($rendezVous, Priorite::from($data['priorite']), $data['motif_priorite'] ?? null), 'file.priorite');
    }

    private function agir(Request $request, RendezVous $rdv, \Closure $action, string $flash): RedirectResponse
    {
        abort_unless($request->user()->canAccessService($rdv->service_id), 403);

        try {
            $action();
        } catch (QueueException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('success', $flash);
    }

    /**
     * @return array<string, mixed>
     */
    private function ligne(RendezVous $r, ?int $rang): array
    {
        return [
            'id' => $r->id, 'rang' => $rang, 'numero_ticket' => $r->numero_ticket,
            'heure' => $r->heure_vague_prevue ? substr($r->heure_vague_prevue, 0, 5) : null,
            'checkin' => $r->heure_arrivee_checkin?->format('H:i'),
            'statut' => $r->statut->value, 'priorite' => $r->priorite->value, 'motif_priorite' => $r->motif_priorite,
            'patient' => $r->patient->only(['id', 'nom', 'prenom', 'ipp', 'nb_absences']),
            'type' => $r->typeConsultation->only(['libelle', 'couleur']),
            'medecin' => $r->medecin?->user?->name,
        ];
    }
}
