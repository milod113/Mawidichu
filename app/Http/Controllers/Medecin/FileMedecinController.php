<?php

namespace App\Http\Controllers\Medecin;

use App\Enums\StatutRdv;
use App\Http\Controllers\Controller;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Services\ConsultationService;
use App\Services\QueueException;
use App\Services\QueueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Salle d'attente du médecin : appeler le suivant / un patient précis, rappeler,
 * signaler un patient qui ne répond pas, démarrer la consultation.
 */
class FileMedecinController extends Controller
{
    public function index(Request $request, QueueService $queue): Response
    {
        $medecin = $this->medecin($request);

        $rdvs = RendezVous::where('service_id', $medecin->service_id)->whereDate('date_rdv', today())
            ->whereIn('statut', [...QueueService::EN_SALLE, StatutRdv::Appele, StatutRdv::EnConsultation, StatutRdv::EnAttenteRetour])
            ->where(fn ($q) => $q->whereNull('medecin_id')->orWhere('medecin_id', $medecin->id))
            ->with(['patient:id,nom,prenom,ipp', 'typeConsultation:id,libelle', 'constantes:id,rdv_id', 'consultation:id,rdv_id'])->get();

        $presents = $queue->ordonner($rdvs->filter(fn ($r) => in_array($r->statut, QueueService::EN_SALLE, true)));
        $reste = $rdvs->reject(fn ($r) => in_array($r->statut, QueueService::EN_SALLE, true))->sortBy('heure_appel');

        return Inertia::render('Medecin/File', [
            'preconsultation' => (bool) $medecin->service->preconsultation_active,
            'rdvs' => $presents->concat($reste)->values()->map(fn (RendezVous $r) => [
                'id' => $r->id, 'numero_ticket' => $r->numero_ticket, 'statut' => $r->statut->value, 'priorite' => $r->priorite->value,
                'motif_priorite' => $r->motif_priorite, 'checkin' => $r->heure_arrivee_checkin?->format('H:i'),
                'patient' => $r->patient->only(['id', 'nom', 'prenom', 'ipp']), 'type' => $r->typeConsultation->libelle,
                'consultation_id' => $r->consultation?->id, 'constantes' => $r->constantes !== null,
                'appelable' => in_array($r->statut, $medecin->service->preconsultation_active ? [StatutRdv::PretMedecin] : [StatutRdv::Arrive, StatutRdv::PretMedecin], true),
            ]),
        ]);
    }

    public function suivant(Request $request, ConsultationService $service): RedirectResponse
    {
        $medecin = $this->medecin($request);
        $rdv = $service->suivant($medecin);
        if (! $rdv) {
            return back()->withErrors(['file' => 'file.erreur.personne']);
        }

        return $this->agir(fn () => $service->appeler($rdv, $medecin), 'medecin.called');
    }

    public function appeler(Request $request, RendezVous $rendezVous, ConsultationService $service): RedirectResponse
    {
        $medecin = $this->medecin($request);

        return $this->agir(fn () => $service->appeler($rendezVous, $medecin), 'medecin.called');
    }

    public function rappeler(Request $request, RendezVous $rendezVous, ConsultationService $service): RedirectResponse
    {
        $medecin = $this->medecin($request);

        return $this->agir(fn () => $service->rappeler($rendezVous, $medecin), 'medecin.recalled');
    }

    public function absent(Request $request, RendezVous $rendezVous, ConsultationService $service): RedirectResponse
    {
        $medecin = $this->medecin($request);

        return $this->agir(fn () => $service->absentALappel($rendezVous, $medecin), 'medecin.waiting_return');
    }

    public function demarrer(Request $request, RendezVous $rendezVous, ConsultationService $service): RedirectResponse
    {
        $medecin = $this->medecin($request);

        try {
            $consultation = $service->demarrer($rendezVous, $medecin);
        } catch (QueueException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('medecin.consultations.show', $consultation);
    }

    private function agir(\Closure $action, string $flash): RedirectResponse
    {
        try {
            $action();
        } catch (QueueException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('success', $flash);
    }

    private function medecin(Request $request): Medecin
    {
        $medecin = $request->user()->medecin;
        abort_if($medecin === null, 403);

        return $medecin->loadMissing('service');
    }
}
