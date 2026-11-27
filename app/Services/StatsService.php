<?php

namespace App\Services;

use App\Enums\CodeConsultation;
use App\Enums\Grade;
use App\Enums\StatutRdv;
use App\Models\Medecin;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Indicateurs de la direction et des chefs de service (§8). Les périmètres sont
 * contrôlés ici : un chef de service ne voit jamais que son service.
 */
class StatsService
{
    public function __construct(private AvailabilityService $availability) {}

    /**
     * Services visibles par l'utilisateur.
     *
     * @return Collection<int, Service>
     */
    public function services(User $user): Collection
    {
        $q = Service::where('is_active', true)->orderBy('nom');

        if (! $user->hasAnyRole(['super_admin', 'direction'])) {
            $q->where('id', $user->medecin?->service_id ?? 0);
        }

        return $q->get();
    }

    /**
     * Normalise les filtres reçus (dates par défaut : 30 derniers jours).
     *
     * @param  array<string, mixed>  $f
     * @return array{du: string, au: string, service_id: ?int, medecin_id: ?int, grade: ?string}
     */
    public function filtres(User $user, array $f): array
    {
        $au = Carbon::parse($f['au'] ?? today())->startOfDay();
        $du = Carbon::parse($f['du'] ?? $au->copy()->subDays(29))->startOfDay();
        if ($du->gt($au)) {
            [$du, $au] = [$au, $du];
        }

        $service = $this->services($user)->firstWhere('id', (int) ($f['service_id'] ?? 0));

        return [
            'du' => $du->toDateString(), 'au' => $au->toDateString(),
            'service_id' => $service?->id,
            'medecin_id' => ! empty($f['medecin_id']) ? (int) $f['medecin_id'] : null,
            'grade' => in_array($f['grade'] ?? null, Grade::values(), true) ? $f['grade'] : null,
        ];
    }

    /**
     * @param  array{du: string, au: string, service_id: ?int, medecin_id: ?int, grade: ?string}  $f
     * @return array<string, mixed>
     */
    public function calculer(User $user, array $f): array
    {
        $services = $this->services($user);
        $ids = $f['service_id'] ? [$f['service_id']] : $services->modelKeys();

        $rdvs = RendezVous::query()
            ->whereIn('service_id', $ids)
            ->whereDate('date_rdv', '>=', $f['du'])->whereDate('date_rdv', '<=', $f['au'])
            ->when($f['medecin_id'], fn ($q) => $q->where('medecin_id', $f['medecin_id']))
            ->when($f['grade'], fn ($q) => $q->whereHas('medecin', fn ($m) => $m->where('grade', $f['grade'])))
            ->with(['typeConsultation:id,code', 'medecin:id,grade,service_id', 'medecin.user:id,name', 'service:id,nom'])
            ->get();

        $statut = fn (RendezVous $r, StatutRdv ...$s) => in_array($r->statut, $s, true);

        $volume = [
            'prevus' => $rdvs->count(),
            'realises' => $rdvs->filter(fn ($r) => $statut($r, StatutRdv::Termine))->count(),
            'absents' => $rdvs->filter(fn ($r) => $statut($r, StatutRdv::Absent))->count(),
            'reportes' => $rdvs->filter(fn ($r) => $statut($r, StatutRdv::Reporte, StatutRdv::AReplanifier))->count(),
        ];
        $volume['taux_absence'] = $volume['prevus'] > 0 ? round($volume['absents'] / $volume['prevus'] * 100, 1) : 0.0;

        $repartition = [];
        foreach (CodeConsultation::cases() as $code) {
            $repartition[$code->value] = $rdvs->filter(fn ($r) => $r->typeConsultation->code === $code)->count();
        }

        $parJour = $rdvs->groupBy(fn ($r) => $r->date_rdv->toDateString())->sortKeys()->map(fn (Collection $j, string $date) => [
            'date' => $date, 'prevus' => $j->count(),
            'realises' => $j->filter(fn ($r) => $statut($r, StatutRdv::Termine))->count(),
            'absents' => $j->filter(fn ($r) => $statut($r, StatutRdv::Absent))->count(),
        ])->values();

        $charge = $rdvs->whereNotNull('medecin_id')->groupBy('medecin_id')->map(function (Collection $g) {
            $m = $g->first()->medecin;

            return [
                'medecin_id' => $m->id, 'medecin' => $m->user?->name, 'grade' => $m->grade->value, 'senior' => $m->grade !== Grade::Resident,
                'service' => $g->first()->service->nom, 'total' => $g->count(),
                'realises' => $g->filter(fn ($r) => $r->statut === StatutRdv::Termine)->count(),
                'attente_moyenne' => $this->attenteMoyenne($g),
            ];
        })->sortByDesc('total')->values();

        $grades = $charge->groupBy('grade')->map(fn ($g, $grade) => ['grade' => $grade, 'praticiens' => $g->count(), 'total' => $g->sum('total'), 'realises' => $g->sum('realises')])->values();

        return [
            'filtres' => $f,
            'volume' => $volume,
            'repartition' => $repartition,
            'par_jour' => $parJour,
            'attente_moyenne' => $this->attenteMoyenne($rdvs),
            'delais' => $this->delais($services->when($f['service_id'], fn ($c) => $c->where('id', $f['service_id']))),
            'charge' => $charge,
            'grades' => $grades,
            'seniors_residents' => [
                'seniors' => $charge->where('senior', true)->sum('total'),
                'residents' => $charge->where('senior', false)->sum('total'),
            ],
        ];
    }

    /**
     * Temps d'attente moyen sur place, en minutes (check-in → appel) ; null sans mesure.
     *
     * @param  Collection<int, RendezVous>  $rdvs
     */
    private function attenteMoyenne(Collection $rdvs): ?float
    {
        $mesures = $rdvs->filter(fn ($r) => $r->heure_arrivee_checkin && $r->heure_appel)
            ->map(fn ($r) => max(0, $r->heure_arrivee_checkin->diffInSeconds($r->heure_appel, false)) / 60);

        return $mesures->isEmpty() ? null : round($mesures->avg(), 1);
    }

    /**
     * Délai (jours) avant la prochaine place de première consultation, par service.
     *
     * @param  Collection<int, Service>  $services
     * @return list<array{service: string, date: ?string, jours: ?int}>
     */
    private function delais(Collection $services): array
    {
        return $services->map(function (Service $s) {
            $type = $s->typesConsultation()->where('code', CodeConsultation::PremiereConsultation->value)->where('is_active', true)->first();
            $entree = $type ? $this->availability->premiereDisponibilite($s, $type) : null;

            return [
                'service' => $s->nom, 'date' => $entree['date'] ?? null,
                'jours' => $entree ? (int) today()->diffInDays(Carbon::parse($entree['date'])) : null,
            ];
        })->values()->all();
    }

    /**
     * Médecins sélectionnables pour les filtres.
     *
     * @return Collection<int, Medecin>
     */
    public function medecins(Collection $services): Collection
    {
        return Medecin::whereIn('service_id', $services->modelKeys())->with('user:id,name')->get();
    }
}
