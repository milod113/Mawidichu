<?php

namespace App\Http\Controllers\Tv;

use App\Enums\StatutRdv;
use App\Http\Controllers\Controller;
use App\Models\RendezVous;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écran TV de la salle d'attente (compte « ecran_tv », mode kiosque) : appels récents,
 * filtrables par aile / étage, ticket ou nom selon le réglage du service.
 */
class TvController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('Tv/Display', [
            'ailes' => Service::where('is_active', true)->whereNotNull('aile')->distinct()->orderBy('aile')->pluck('aile'),
            'aile' => $request->query('aile'),
            'appels' => $this->appels($request->query('aile')),
            'refresh' => config('mawid.tv_refresh_secondes'),
        ]);
    }

    public function donnees(Request $request): JsonResponse
    {
        return response()->json($this->appels($request->query('aile')));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function appels(?string $aile): array
    {
        return RendezVous::whereDate('date_rdv', today())->whereIn('statut', [StatutRdv::Appele, StatutRdv::EnConsultation])
            ->whereNotNull('heure_appel')
            ->whereHas('service', fn ($q) => $aile ? $q->where('aile', $aile) : $q)
            ->with(['service:id,nom,aile,affichage_tv_mode', 'patient:id,nom,prenom', 'box:id,numero_box,nom_affichage'])
            ->orderByDesc('heure_appel')->limit(config('mawid.tv_appels_affiches'))->get()
            ->map(fn (RendezVous $r) => [
                'id' => $r->id, 'ticket' => $r->numero_ticket, 'service_id' => $r->service_id, 'aile' => $r->service->aile,
                'nom' => $r->service->affichage_tv_mode?->value === 'nom' ? mb_strtoupper($r->patient->nom).' '.$r->patient->prenom : null,
                'box' => $r->box ? ($r->box->nom_affichage ?? 'Box '.$r->box->numero_box) : null,
                'service_nom' => $r->service->nom, 'at' => $r->heure_appel->toIso8601String(),
            ])->values()->all();
    }
}
