<?php

namespace App\Http\Controllers\Accueil;

use App\Enums\Sexe;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\BookingRules;
use App\Services\IppGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Identité administrative des patients (IPP unique, partagé entre services).
 * Aucune donnée médicale ici : elle reste cloisonnée par service.
 */
class PatientController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $patients = Patient::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
                $query->where(fn ($w) => $w->where('ipp', 'like', $like)->orWhere('nom', 'like', $like)
                    ->orWhere('prenom', 'like', $like)->orWhere('nom_ar', 'like', $like)->orWhere('prenom_ar', 'like', $like)
                    ->orWhere('telephone', 'like', $like)->orWhere('nin', 'like', $like)->orWhere('numero_chifa', 'like', $like));
            })
            ->orderBy('nom')->orderBy('prenom')
            ->paginate(15)->withQueryString();

        return Inertia::render('Accueil/Patients/Index', [
            'patients' => $patients,
            'q' => $q,
            'canEdit' => $request->user()->hasAnyRole(['secretaire', 'super_admin']),
        ]);
    }

    public function store(Request $request, IppGenerator $ipp): RedirectResponse
    {
        $data = $this->validated($request);

        $doublon = Patient::where('nom', $data['nom'])->where('prenom', $data['prenom'])
            ->when($data['date_naissance'] ?? null, fn ($q, $d) => $q->whereDate('date_naissance', $d))->first();

        if ($doublon && ! $request->boolean('confirmer_doublon')) {
            throw ValidationException::withMessages(['nom' => 'patient.doublon:'.$doublon->ipp]);
        }

        $patient = Patient::create([...$data, 'ipp' => $ipp->next()]);

        return redirect()->route('accueil.patients.show', $patient)->with('success', 'patient.created');
    }

    public function show(Request $request, Patient $patient, BookingRules $rules): Response
    {
        $rdvs = $patient->rendezVous()->with(['service:id,nom,nom_ar,code', 'typeConsultation:id,libelle', 'medecin.user:id,name'])
            ->orderByDesc('date_rdv')->limit(50)->get()
            ->map(fn ($r) => [
                'id' => $r->id, 'numero_ticket' => $r->numero_ticket, 'date_rdv' => $r->date_rdv->toDateString(),
                'heure' => $r->heure_vague_prevue ? substr($r->heure_vague_prevue, 0, 5) : null,
                'statut' => $r->statut->value, 'service' => $r->service->only(['nom', 'nom_ar']),
                'type' => $r->typeConsultation->libelle, 'medecin' => $r->medecin?->user?->name,
            ]);

        return Inertia::render('Accueil/Patients/Show', [
            'patient' => $patient,
            'rendezVous' => $rdvs,
            'canEdit' => $request->user()->hasAnyRole(['secretaire', 'super_admin']),
            'canBook' => $request->user()->hasAnyRole(['secretaire', 'super_admin']),
            'canDossier' => $request->user()->hasAnyRole(['medecin', 'chef_service', 'super_admin']),
        ]);
    }

    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $patient->update($this->validated($request, $patient));

        return back()->with('success', 'patient.updated');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Patient $patient = null): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'nom_ar' => ['nullable', 'string', 'max:255'],
            'prenom_ar' => ['nullable', 'string', 'max:255'],
            'date_naissance' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sexe' => ['nullable', Rule::enum(Sexe::class)],
            'telephone' => ['nullable', 'string', 'max:30'],
            'nin' => ['nullable', 'string', 'max:30'],
            'numero_chifa' => ['nullable', 'string', 'max:30'],
            'wilaya' => ['nullable', 'string', 'max:255'],
            'commune' => ['nullable', 'string', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
