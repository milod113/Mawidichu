<?php

namespace App\Http\Controllers\Accueil;

use App\Http\Controllers\Controller;
use App\Models\RendezVous;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TicketController extends Controller
{
    /**
     * Ticket de RDV en PDF : 80 mm (thermique) ou A5, avec la liste des
     * bilans / documents à ramener (type de consultation + documents par défaut du service).
     */
    public function show(Request $request, RendezVous $rendezVous, string $format): Response
    {
        abort_unless(in_array($format, ['80mm', 'a5'], true), 404);
        abort_unless($request->user()->canAccessService($rendezVous->service_id), 403);

        $rendezVous->load(['patient', 'service', 'typeConsultation', 'medecin.user:id,name', 'box']);

        $apporter = collect(preg_split('/\r?\n/', (string) $rendezVous->typeConsultation->bilans_requis_text))
            ->merge($rendezVous->service->documents_defaut_json ?? [])
            ->map(fn ($l) => trim((string) $l))->filter()->unique()->values();

        $pdf = Pdf::loadView('pdf.ticket', [
            'rdv' => $rendezVous, 'apporter' => $apporter, 'etab' => config('mawid.etablissement'), 'compact' => $format === '80mm',
        ])->setPaper($format === '80mm' ? [0, 0, 226.77, 520] : 'a5');

        return $pdf->stream("ticket-{$rendezVous->numero_ticket}.pdf");
    }
}
