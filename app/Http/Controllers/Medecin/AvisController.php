<?php

namespace App\Http\Controllers\Medecin;

use App\Enums\Grade;
use App\Enums\StatutAvis;
use App\Enums\TypeAvis;
use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\DemandeAvis;
use App\Models\Medecin;
use App\Models\Service;
use App\Services\DossierAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Demandes d'avis : senior ↔ interne (même service) et inter-spécialités
 * (ouvre le dossier au service cible pour une durée limitée et configurable).
 */
class AvisController extends Controller
{
    public function index(Request $request): Response
    {
        $medecin = $this->medecin($request);

        $map = fn (DemandeAvis $a) => [
            'id' => $a->id, 'type' => $a->type_avis->value, 'statut' => $a->statut->value, 'motif' => $a->motif, 'reponse' => $a->reponse_avis,
            'patient' => $a->patient->only(['id', 'ipp', 'nom', 'prenom']), 'consultation_id' => $a->consultation_id,
            'de' => $a->medecinDemandeur->user?->name.' — '.$a->serviceDemandeur->nom, 'vers' => $a->medecinCible?->user?->name ?? $a->serviceCible->nom,
            'expire_le' => $a->expire_le?->format('Y-m-d H:i'), 'expire' => $a->expire_le !== null && $a->expire_le->isPast(),
            'date' => $a->created_at->format('Y-m-d H:i'),
        ];
        $with = ['patient:id,ipp,nom,prenom', 'medecinDemandeur.user:id,name', 'serviceDemandeur:id,nom', 'medecinCible.user:id,name', 'serviceCible:id,nom'];

        $recues = DemandeAvis::where('service_cible_id', $medecin->service_id)
            ->where(fn ($q) => $q->whereNull('medecin_cible_id')->orWhere('medecin_cible_id', $medecin->id))
            ->with($with)->latest('id')->limit(100)->get()->map($map);
        $envoyees = DemandeAvis::where('medecin_demandeur_id', $medecin->id)->with($with)->latest('id')->limit(100)->get()->map($map);

        return Inertia::render('Medecin/Avis', ['recues' => $recues, 'envoyees' => $envoyees]);
    }

    public function store(Request $request, Consultation $consultation, DossierAccess $acces): RedirectResponse
    {
        $medecin = $this->medecin($request);
        abort_unless($consultation->service_id === $medecin->service_id, 403);

        $data = $request->validate([
            'type_avis' => ['required', Rule::enum(TypeAvis::class)],
            'service_cible_id' => ['nullable', 'integer', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'medecin_cible_id' => ['nullable', 'integer', 'exists:medecins,id'],
            'motif' => ['required', 'string', 'max:2000'],
        ]);

        $type = TypeAvis::from($data['type_avis']);

        if ($type === TypeAvis::SeniorInterne) {
            $senior = Medecin::where('service_id', $medecin->service_id)->where('id', '!=', $medecin->id)->find($data['medecin_cible_id'] ?? 0);
            if (! $senior || $senior->grade === Grade::Resident) {
                return back()->withErrors(['medecin_cible_id' => 'avis.erreur.senior']);
            }
            $cible = ['service_cible_id' => $medecin->service_id, 'medecin_cible_id' => $senior->id, 'expire_le' => null];
        } else {
            if (empty($data['service_cible_id']) || (int) $data['service_cible_id'] === $medecin->service_id) {
                return back()->withErrors(['service_cible_id' => 'avis.erreur.service']);
            }
            $cible = [
                'service_cible_id' => $data['service_cible_id'], 'medecin_cible_id' => $data['medecin_cible_id'] ?? null,
                'expire_le' => now()->addDays(config('mawid.avis_validite_jours')),
            ];
        }

        DemandeAvis::create($cible + [
            'consultation_id' => $consultation->id, 'patient_id' => $consultation->patient_id,
            'service_demandeur_id' => $medecin->service_id, 'medecin_demandeur_id' => $medecin->id,
            'type_avis' => $type, 'motif' => $data['motif'], 'statut' => StatutAvis::EnAttente,
        ]);

        return back()->with('success', 'avis.sent');
    }

    public function repondre(Request $request, DemandeAvis $avis): RedirectResponse
    {
        $medecin = $this->medecin($request);
        abort_unless($avis->service_cible_id === $medecin->service_id && ($avis->medecin_cible_id === null || $avis->medecin_cible_id === $medecin->id), 403);
        abort_if($avis->expire_le !== null && $avis->expire_le->isPast(), 403);

        $data = $request->validate(['reponse_avis' => ['required', 'string', 'max:5000']]);
        $avis->update(['reponse_avis' => $data['reponse_avis'], 'statut' => StatutAvis::Repondu]);

        if ($avis->type_avis === TypeAvis::SeniorInterne) {
            $avis->consultation->update(['superviseur_senior_id' => $medecin->id]);
        }

        return back()->with('success', 'avis.answered');
    }

    private function medecin(Request $request): Medecin
    {
        abort_if($request->user()->medecin === null, 403);

        return $request->user()->medecin;
    }
}
