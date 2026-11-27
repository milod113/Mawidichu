<?php

namespace App\Http\Controllers\Dossier;

use App\Enums\TypeDocument;
use App\Http\Controllers\Controller;
use App\Models\DocumentPatient;
use App\Models\Patient;
use App\Services\DossierAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pièces jointes (lettre d'orientation scannée, bilans, imagerie…) : stockées hors
 * du dossier public, servies uniquement après contrôle du cloisonnement.
 */
class DocumentController extends Controller
{
    public function store(Request $request, Patient $patient, DossierAccess $acces): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'type_document' => ['required', Rule::enum(TypeDocument::class)],
            'titre' => ['required', 'string', 'max:255'],
            'consultation_id' => ['nullable', 'integer', 'exists:consultations,id'],
            'rdv_id' => ['nullable', 'integer', 'exists:rendez_vous,id'],
            'fichier' => ['required', 'file', 'max:'.config('mawid.upload_max_kb'), 'mimes:'.implode(',', config('mawid.upload_extensions'))],
        ]);

        // Le médecin dépose dans son service ; l'accueil dans un service auquel son poste a accès.
        abort_unless($user->canAccessService((int) $data['service_id']), 403);

        $fichier = $request->file('fichier');
        $path = $fichier->store("patients/{$patient->id}", 'local');

        DocumentPatient::create([
            'patient_id' => $patient->id, 'service_id' => $data['service_id'], 'type_document' => $data['type_document'],
            'titre' => $data['titre'], 'consultation_id' => $data['consultation_id'] ?? null, 'rdv_id' => $data['rdv_id'] ?? null,
            'fichier_path' => $path, 'uploaded_by' => $user->id,
        ]);

        return back()->with('success', 'document.uploaded');
    }

    public function download(Request $request, DocumentPatient $document, DossierAccess $acces): StreamedResponse
    {
        $this->autoriser($request, $document, $acces);
        abort_unless(Storage::disk('local')->exists($document->fichier_path), 404);

        $ext = pathinfo($document->fichier_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download($document->fichier_path, str($document->titre)->slug()->append('.'.$ext)->toString());
    }

    public function destroy(Request $request, DocumentPatient $document, DossierAccess $acces): RedirectResponse
    {
        $this->autoriser($request, $document, $acces);
        $document->delete(); // suppression douce : le fichier reste sur disque, la trace dans audit_logs

        return back()->with('success', 'document.deleted');
    }

    private function autoriser(Request $request, DocumentPatient $document, DossierAccess $acces): void
    {
        $user = $request->user();
        $ok = $acces->canViewService($user, $document->patient, $document->service_id)
            || ($user->hasRole('secretaire') && $user->canAccessService($document->service_id));
        abort_unless($ok, 403);
    }
}
