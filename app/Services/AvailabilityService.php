<?php

namespace App\Services;

use App\Enums\CodeConsultation;
use App\Enums\ModePlanification;
use App\Enums\Priorite;
use App\Enums\SurbookingUnite;
use App\Models\ExceptionPlanning;
use App\Models\JourFerie;
use App\Models\Medecin;
use App\Models\PlanningType;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\TypeConsultation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Moteur de disponibilités (cahier des charges §3.2).
 *
 * À partir des plannings types actifs, retire les jours fériés et les absences
 * (exceptions), puis calcule pour chaque vacation la capacité restante selon son
 * mode : quota par vagues, créneaux dynamiques ou mixte. Aucune durée, aucun
 * quota ni aucun jour n'est codé ici : tout vient de la base.
 */
class AvailabilityService
{
    public const POOL_PLANIFIE = 'planifie';

    public const POOL_RESERVE = 'reserve';

    /**
     * Les RDV d'orientation interne et de retour de bilan puisent dans le quota
     * « sans rendez-vous » du mode mixte ; les autres dans le quota planifié.
     */
    public static function pool(CodeConsultation $code, ?Priorite $priorite = null): string
    {
        return $code === CodeConsultation::RetourBilan || $priorite === Priorite::OrientationInterServices
            ? self::POOL_RESERVE
            : self::POOL_PLANIFIE;
    }

    /**
     * @return Collection<int, array<string, mixed>> vacations triées par date puis heure
     */
    public function forService(
        Service $service,
        TypeConsultation $type,
        \DateTimeInterface|string $from,
        \DateTimeInterface|string $to,
        ?Medecin $medecin = null,
        ?Priorite $priorite = null,
        ?int $excludeRdvId = null,
    ): Collection {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();
        if ($from->lt(today())) {
            $from = today();
        }
        if ($to->lt($from)) {
            return collect();
        }

        $plannings = PlanningType::query()
            ->where('service_id', $service->id)->where('is_active', true)
            ->when($medecin, fn ($q) => $q->where('medecin_id', $medecin->id))
            ->with(['medecin.user:id,name', 'box:id,numero_box,nom_affichage'])
            ->get()->groupBy('jour_semaine');

        if ($plannings->isEmpty()) {
            return collect();
        }

        $feries = JourFerie::all();
        $exceptions = ExceptionPlanning::where('service_id', $service->id)
            ->whereDate('date_debut', '<=', $to)->whereDate('date_fin', '>=', $from)->get();

        $liberant = config('mawid.statuts_liberant_place');
        $rdvs = RendezVous::query()
            ->where('service_id', $service->id)
            ->whereDate('date_rdv', '>=', $from->toDateString())
            ->whereDate('date_rdv', '<=', $to->toDateString())
            ->whereNotIn('statut', $liberant)
            ->when($excludeRdvId, fn ($q) => $q->where('id', '!=', $excludeRdvId))
            ->with('typeConsultation:id,code,duree_minutes')
            ->get()->groupBy(fn (RendezVous $r) => $r->date_rdv->toDateString());

        $entries = collect();

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            if ($this->estFerie($feries, $date)) {
                continue;
            }

            foreach ($plannings->get($date->dayOfWeek, collect()) as $planning) {
                if ($this->estBloque($exceptions, $planning, $date)) {
                    continue;
                }

                $entries->push($this->entry(
                    $planning, $date, $rdvs->get($date->toDateString(), collect()), $service, $type, $priorite,
                ));
            }
        }

        return $entries->sortBy([['date', 'asc'], ['debut', 'asc']])->values();
    }

    /**
     * Première date réservable (null si rien dans l'horizon configuré).
     *
     * @return array<string, mixed>|null
     */
    public function premiereDisponibilite(Service $service, TypeConsultation $type): ?array
    {
        $horizon = config('mawid.horizon_jours');

        return $this->forService($service, $type, today(), today()->addDays($horizon))
            ->first(fn (array $e) => $e['bookable']);
    }

    /**
     * @param  Collection<int, JourFerie>  $feries
     */
    private function estFerie(Collection $feries, Carbon $date): bool
    {
        return $feries->contains(fn (JourFerie $f) => $f->date_ferie->isSameDay($date)
            || ($f->est_recurrent && $f->date_ferie->month === $date->month && $f->date_ferie->day === $date->day));
    }

    /**
     * @param  Collection<int, ExceptionPlanning>  $exceptions
     */
    private function estBloque(Collection $exceptions, PlanningType $planning, Carbon $date): bool
    {
        return $exceptions->contains(function (ExceptionPlanning $e) use ($planning, $date) {
            if ($date->lt($e->date_debut->copy()->startOfDay()) || $date->gt($e->date_fin->copy()->startOfDay())) {
                return false;
            }

            // Absence de tout le service, ou d'un médecin précis.
            return $e->medecin_id === null || $e->medecin_id === $planning->medecin_id;
        });
    }

    /**
     * @param  Collection<int, RendezVous>  $jour
     * @return array<string, mixed>
     */
    private function entry(PlanningType $p, Carbon $date, Collection $jour, Service $service, TypeConsultation $type, ?Priorite $priorite): array
    {
        $debut = $this->minutes($p->heure_debut);
        $fin = $this->minutes($p->heure_fin);
        $mode = $p->mode_planification ?? $service->mode_planification;

        $dansVacation = $jour->filter(function (RendezVous $r) use ($p, $debut, $fin) {
            $meme = $p->medecin_id !== null ? $r->medecin_id === $p->medecin_id : $r->box_id === $p->box_id;
            if (! $meme) {
                return false;
            }
            if ($r->heure_vague_prevue === null) {
                return true;
            }
            $m = $this->minutes($r->heure_vague_prevue);

            return $m >= $debut && $m < $fin;
        });

        $base = [
            'key' => $p->id.'|'.$date->toDateString(),
            'planning_id' => $p->id,
            'date' => $date->toDateString(),
            'jour_semaine' => $p->jour_semaine,
            'debut' => $this->hhmm($debut),
            'fin' => $this->hhmm($fin),
            'mode' => $mode->value,
            'medecin_id' => $p->medecin_id,
            'medecin_nom' => $p->medecin?->user?->name,
            'grade' => $p->medecin?->grade?->value,
            'box_id' => $p->box_id,
            'box_nom' => $p->box ? ($p->box->nom_affichage ?? 'Box '.$p->box->numero_box) : null,
            'slots' => null,
            'pool' => self::POOL_PLANIFIE,
        ];

        if ($mode === ModePlanification::CreneauxDynamiques) {
            return [...$base, ...$this->creneaux($dansVacation, $debut, $fin, $type)];
        }

        $pool = $mode === ModePlanification::Mixte ? self::pool($type->code, $priorite) : self::POOL_PLANIFIE;
        $dansPool = $mode === ModePlanification::Mixte
            ? $dansVacation->filter(fn (RendezVous $r) => self::pool($r->typeConsultation->code, $r->priorite) === $pool)
            : $dansVacation;

        $quota = (int) ($pool === self::POOL_RESERVE ? $p->quota_sans_rdv : $p->quota_rdv);
        $pris = $dansPool->count();
        $max = $quota + $this->surbooking($p, $quota);
        $prochaine = $debut;
        if ($pool === self::POOL_PLANIFIE && $p->taille_vague && $p->intervalle_vague_min) {
            $prochaine = min($fin - 1, $debut + intdiv($pris, $p->taille_vague) * $p->intervalle_vague_min);
        }

        return [...$base,
            'pool' => $pool,
            'quota' => $quota,
            'taken' => $pris,
            'remaining' => max(0, $quota - $pris),
            'surbooking_remaining' => max(0, $max - max($pris, $quota)),
            'next_time' => $this->hhmm($prochaine),
            'bookable' => $pris < $max,
        ];
    }

    /**
     * Créneaux libres pour la durée du type demandé, sans chevaucher les RDV existants
     * (chacun occupe la durée de son propre type).
     *
     * @param  Collection<int, RendezVous>  $dansVacation
     * @return array<string, mixed>
     */
    private function creneaux(Collection $dansVacation, int $debut, int $fin, TypeConsultation $type): array
    {
        $duree = max(1, $type->duree_minutes);

        $occupes = $dansVacation->map(function (RendezVous $r) use ($debut) {
            $d = $r->heure_vague_prevue ? $this->minutes($r->heure_vague_prevue) : $debut;

            return [$d, $d + $r->typeConsultation->duree_minutes];
        });

        $libres = [];
        for ($t = $debut; $t + $duree <= $fin; $t += $duree) {
            $chevauche = $occupes->contains(fn (array $o) => $t < $o[1] && $t + $duree > $o[0]);
            if (! $chevauche) {
                $libres[] = $this->hhmm($t);
            }
        }

        $total = intdiv($fin - $debut, $duree);

        return [
            'quota' => $total,
            'taken' => $dansVacation->count(),
            'remaining' => count($libres),
            'surbooking_remaining' => 0,
            'next_time' => $libres[0] ?? null,
            'slots' => $libres,
            'bookable' => $libres !== [],
        ];
    }

    private function surbooking(PlanningType $p, int $quota): int
    {
        $m = $p->medecin;
        if (! $m || ! $m->tolere_surbooking || ! $m->valeur_surbooking) {
            return 0;
        }

        return $m->surbooking_unite === SurbookingUnite::Pourcentage
            ? (int) ceil($quota * $m->valeur_surbooking / 100)
            : $m->valeur_surbooking;
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }

    private function hhmm(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
