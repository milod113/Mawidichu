<?php

namespace App\Http\Controllers\Stats;

use App\Enums\Grade;
use App\Exports\StatsExport;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\StatsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Tableaux de bord Direction / chef de service avec exports officiels PDF et Excel.
 */
class StatsController extends Controller
{
    public function index(Request $request, StatsService $stats): Response
    {
        $services = $stats->services($request->user());
        $f = $stats->filtres($request->user(), $request->query());

        return Inertia::render('Stats/Index', [
            'stats' => $stats->calculer($request->user(), $f),
            'services' => $services->map(fn ($s) => $s->only(['id', 'nom'])),
            'medecins' => $stats->medecins($services)->map(fn ($m) => ['id' => $m->id, 'name' => $m->user?->name, 'service_id' => $m->service_id]),
            'grades' => Grade::values(),
        ]);
    }

    public function pdf(Request $request, StatsService $stats)
    {
        $data = $stats->calculer($request->user(), $stats->filtres($request->user(), $request->query()));
        AuditLog::record('export_stats_pdf', $request->user(), null, $data['filtres']);

        return Pdf::loadView('pdf.stats', ['s' => $data, 'etab' => config('mawid.etablissement'), 'compact' => false, 'par' => $request->user()->name])
            ->setPaper('a4')->stream('statistiques-'.$data['filtres']['du'].'-'.$data['filtres']['au'].'.pdf');
    }

    public function excel(Request $request, StatsService $stats)
    {
        $data = $stats->calculer($request->user(), $stats->filtres($request->user(), $request->query()));
        AuditLog::record('export_stats_excel', $request->user(), null, $data['filtres']);

        return Excel::download(new StatsExport($data), 'statistiques-'.$data['filtres']['du'].'-'.$data['filtres']['au'].'.xlsx');
    }
}
