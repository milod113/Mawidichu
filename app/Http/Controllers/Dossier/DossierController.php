<?php

namespace App\Http\Controllers\Dossier;

use App\Enums\TypeDocument;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Service;
use App\Services\DossierAccess;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dossier médical d'un patient, lu par service : jamais plus que ce que
 * DossierAccess autorise. Chaque ouverture est journalisée.
 */
class DossierController extends Controller
{
    public function show(Request $request, Patient $patient, DossierAccess $acces): Response
    {
        $user = $request->user();
        $ids = $acces->serviceIds($user, $patient);
        abort_if($ids !== null && $ids->isEmpty(), 403);

        AuditLog::record('dossier_consulte', $patient, null, ['services' => $ids?->all() ?? 'tous']);

        $consultations = $acces->consultations($user, $patient)->with(['service:id,nom,nom_ar', 'medecin.user:id,name', 'rendezVous:id,date_rdv'])
            ->latest('id')->get()->map(fn ($c) => [
                'id' => $c->id, 'date' => $c->rendezVous->date_rdv->toDateString(), 'service' => $c->service->nom,
                'medecin' => $c->medecin->user?->name, 'diagnostic' => $c->diagnostic, 'conduite' => $c->conduite_a_tenir,
                'ordonnance' => $c->ordonnance_json ?? [], 'bilans' => $c->bilans_demandes_json ?? [],
            ]);

        $documents = $patient->documents()->when($ids !== null, fn ($q) => $q->whereIn('service_id', $ids->all()))
            ->with('service:id,nom')->latest('id')->get()
            ->map(fn ($d) => ['id' => $d->id, 'titre' => $d->titre, 'type' => $d->type_document->value, 'service' => $d->service->nom, 'date' => $d->created_at->toDateString()]);

        return Inertia::render('Dossier/Show', [
            'patient' => $patient->only(['id', 'ipp', 'nom', 'prenom', 'date_naissance', 'sexe']),
            'consultations' => $consultations, 'documents' => $documents,
            'services' => Service::where('is_active', true)->whereIn('id', $user->medecin ? [$user->medecin->service_id] : Service::pluck('id'))->get(['id', 'nom']),
            'types' => TypeDocument::values(),
            'maxKb' => config('mawid.upload_max_kb'),
        ]);
    }
}
