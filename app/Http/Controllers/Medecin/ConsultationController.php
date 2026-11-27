<?php

namespace App\Http\Controllers\Medecin;

use App\Enums\CodeConsultation;
use App\Enums\StatutRdv;
use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Service;
use App\Services\AvailabilityService;
use App\Services\ConsultationService;
use App\Services\DossierAccess;
use App\Services\QueueException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Formulaire de consultation : examen, diagnostic, ordonnance, bilans, contrôle.
 */
class ConsultationController extends Controller
{
    public function show(Request $request, Consultation $consultation, DossierAccess $acces): Response
    {
        abort_unless($acces->canViewConsultation($request->user(), $consultation), 403);

        $consultation->load(['patient', 'service:id,nom,nom_ar,code', 'medecin.user:id,name', 'rendezVous.constantes', 'rendezVous.typeConsultation',
            'demandesAvis.serviceCible:id,nom', 'demandesAvis.medecinCible.user:id,name', 'documents']);
        $mien = $this->estLeMien($request, $consultation);
        $medecin = $request->user()->medecin;

        $precedentes = $acces->consultations($request->user(), $consultation->patient)->where('id', '!=', $consultation->id)
            ->with(['service:id,nom', 'medecin.user:id,name', 'rendezVous:id,date_rdv'])->latest('id')->limit(10)->get()
            ->map(fn (Consultation $c) => [
                'id' => $c->id, 'date' => $c->rendezVous->date_rdv->toDateString(), 'service' => $c->service->nom,
                'medecin' => $c->medecin->user?->name, 'diagnostic' => $c->diagnostic,
            ]);

        return Inertia::render('Medecin/Consultation', [
            'consultation' => [
                'id' => $consultation->id, 'rdv_id' => $consultation->rdv_id,
                'motif_reel' => $consultation->motif_reel, 'examen_clinique' => $consultation->examen_clinique,
                'diagnostic' => $consultation->diagnostic, 'conduite_a_tenir' => $consultation->conduite_a_tenir,
                'ordonnance' => $consultation->ordonnance_json ?? [], 'bilans' => $consultation->bilans_demandes_json ?? [],
                'delai_controle_jours' => $consultation->delai_controle_jours, 'controle_rdv_id' => $consultation->controle_rdv_id,
                'statut' => $consultation->rendezVous->statut->value,
                'service' => $consultation->service->only(['id', 'nom']), 'medecin' => $consultation->medecin->user?->name,
                'type' => $consultation->rendezVous->typeConsultation->libelle,
            ],
            'patient' => $consultation->patient->only(['id', 'ipp', 'nom', 'prenom', 'date_naissance', 'sexe']),
            'constantes' => $consultation->rendezVous->constantes,
            'precedentes' => $precedentes,
            'avis' => $consultation->demandesAvis->map(fn ($a) => [
                'id' => $a->id, 'type' => $a->type_avis->value, 'statut' => $a->statut->value, 'motif' => $a->motif, 'reponse' => $a->reponse_avis,
                'cible' => $a->medecinCible?->user?->name ?? $a->serviceCible->nom,
            ]),
            'documents' => $consultation->documents->map(fn ($d) => ['id' => $d->id, 'titre' => $d->titre, 'type' => $d->type_document->value]),
            'editable' => $mien && $consultation->rendezVous->statut === StatutRdv::EnConsultation,
            'favoris' => $medecin?->medicaments_favoris_json ?? [],
            'bilansCourants' => config('mawid.bilans_courants'),
            'services' => Service::where('is_active', true)->where('id', '!=', $consultation->service_id)->orderBy('nom')->get(['id', 'nom', 'nom_ar']),
            'seniors' => $consultation->service->medecins()->where('id', '!=', $medecin?->id)->where('grade', '!=', 'Resident')->with('user:id,name')->get()
                ->map(fn ($m) => ['id' => $m->id, 'name' => $m->user?->name, 'grade' => $m->grade->value]),
        ]);
    }

    public function update(Request $request, Consultation $consultation, ConsultationService $service): RedirectResponse
    {
        abort_unless($this->estLeMien($request, $consultation), 403);
        abort_unless($consultation->rendezVous->statut === StatutRdv::EnConsultation, 403);

        $data = $request->validate([
            'motif_reel' => ['nullable', 'string', 'max:5000'], 'examen_clinique' => ['nullable', 'string', 'max:10000'],
            'diagnostic' => ['nullable', 'string', 'max:5000'], 'conduite_a_tenir' => ['nullable', 'string', 'max:10000'],
            'ordonnance' => ['nullable', 'array', 'max:40'],
            'ordonnance.*.medicament' => ['required', 'string', 'max:255'],
            'ordonnance.*.posologie' => ['nullable', 'string', 'max:255'], 'ordonnance.*.duree' => ['nullable', 'string', 'max:100'],
            'bilans' => ['nullable', 'array', 'max:40'], 'bilans.*' => ['required', 'string', 'max:255'],
            'delai_controle_jours' => ['nullable', 'integer', 'between:1,730'],
            'cloturer' => ['sometimes', 'boolean'],
            'controle_direct' => ['nullable', 'array'],
            'controle_direct.planning_id' => ['required_with:controle_direct', 'integer'],
            'controle_direct.date' => ['required_with:controle_direct', 'date_format:Y-m-d', 'after_or_equal:today'],
            'controle_direct.heure' => ['nullable', 'date_format:H:i'],
        ]);

        $champs = [
            'motif_reel' => $data['motif_reel'] ?? null, 'examen_clinique' => $data['examen_clinique'] ?? null,
            'diagnostic' => $data['diagnostic'] ?? null, 'conduite_a_tenir' => $data['conduite_a_tenir'] ?? null,
            'ordonnance_json' => $data['ordonnance'] ?? [], 'bilans_demandes_json' => $data['bilans'] ?? [],
            'delai_controle_jours' => $data['delai_controle_jours'] ?? null,
        ];

        try {
            $service->enregistrer($consultation, $request->user(), $champs, (bool) ($data['cloturer'] ?? false), $data['controle_direct'] ?? null);
        } catch (QueueException $e) {
            return back()->withErrors(['consultation' => $e->getMessage()]);
        }

        if ($data['cloturer'] ?? false) {
            return redirect()->route('medecin.file')->with('success', 'medecin.closed');
        }

        return back()->with('success', 'medecin.saved');
    }

    /**
     * Vacations de contrôle encore disponibles pour ce médecin (programmation directe).
     */
    public function disponibilites(Request $request, Consultation $consultation, AvailabilityService $availability): JsonResponse
    {
        abort_unless($this->estLeMien($request, $consultation), 403);
        $type = $consultation->service->typesConsultation()->where('code', CodeConsultation::Controle->value)->where('is_active', true)->first();
        if (! $type) {
            return response()->json([]);
        }

        $delai = $request->has('delai') ? max(0, min(730, $request->integer('delai'))) : (int) ($consultation->delai_controle_jours ?: 0);
        $du = today()->addDays($delai);
        $entrees = $availability->forService($consultation->service, $type, $du, $du->copy()->addDays(90), $consultation->medecin)
            ->filter(fn ($e) => $e['bookable'])->take(15)->values()
            ->map(fn ($e) => ['planning_id' => $e['planning_id'], 'date' => $e['date'], 'debut' => $e['debut'], 'fin' => $e['fin'],
                'heure' => $e['slots'] !== null ? ($e['slots'][0] ?? null) : null, 'slots' => $e['slots'], 'restantes' => $e['remaining']]);

        return response()->json($entrees);
    }

    public function favoris(Request $request): RedirectResponse
    {
        $medecin = $request->user()->medecin;
        abort_if($medecin === null, 403);
        $data = $request->validate(['favoris' => ['array', 'max:100'], 'favoris.*.medicament' => ['required', 'string', 'max:255'],
            'favoris.*.posologie' => ['nullable', 'string', 'max:255'], 'favoris.*.duree' => ['nullable', 'string', 'max:100']]);
        $medecin->update(['medicaments_favoris_json' => array_values($data['favoris'] ?? [])]);

        return back()->with('success', 'medecin.favoris');
    }

    private function estLeMien(Request $request, Consultation $c): bool
    {
        $m = $request->user()->medecin;

        return $m !== null && $m->service_id === $c->service_id && $request->user()->hasAnyRole(['medecin', 'chef_service']);
    }
}
