<?php

namespace App\Http\Controllers\Medecin;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\RendezVous;
use App\Models\Service;
use App\Services\DossierAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Documents imprimables (A4) : ordonnance, demande de bilan, lettre d'orientation,
 * certificat de présence. Les PDF sont en français (limite de la génération PDF hors-ligne).
 */
class PdfController extends Controller
{
    private const VUES = ['ordonnance' => 'pdf.ordonnance', 'bilan' => 'pdf.bilan', 'orientation' => 'pdf.orientation'];

    public function consultation(Request $request, Consultation $consultation, string $type, DossierAccess $acces): Response
    {
        abort_unless(isset(self::VUES[$type]), 404);
        abort_unless($acces->canViewConsultation($request->user(), $consultation), 403);

        $consultation->load(['patient', 'service', 'medecin.user:id,name', 'rendezVous']);

        $vers = null;
        if ($type === 'orientation') {
            $request->validate(['service_id' => ['required', 'integer', 'exists:services,id'], 'motif' => ['nullable', 'string', 'max:2000']]);
            $vers = Service::findOrFail($request->integer('service_id'));
        }

        return Pdf::loadView(self::VUES[$type], [
            'c' => $consultation, 'etab' => config('mawid.etablissement'), 'compact' => false,
            'vers' => $vers, 'motif' => $request->query('motif'),
        ])->setPaper('a4')->stream("{$type}-{$consultation->patient->ipp}.pdf");
    }

    /**
     * Certificat de présence : aucune donnée médicale, accessible à l'accueil comme au médecin.
     */
    public function certificat(Request $request, RendezVous $rendezVous): Response
    {
        abort_unless($request->user()->canAccessService($rendezVous->service_id), 403);
        $rendezVous->load(['patient', 'service', 'medecin.user:id,name']);

        return Pdf::loadView('pdf.certificat', ['rdv' => $rendezVous, 'etab' => config('mawid.etablissement'), 'compact' => false])
            ->setPaper('a5', 'landscape')->stream("certificat-{$rendezVous->numero_ticket}.pdf");
    }
}
