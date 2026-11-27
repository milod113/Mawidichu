<?php

namespace App\Http\Controllers\Parametrage;

use App\Enums\StatutConfirmation;
use App\Enums\TypeJourFerie;
use App\Http\Controllers\Controller;
use App\Models\JourFerie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administration des jours fériés (super admin) : ajout, correction, et
 * décalage / confirmation d'une fête entière en un clic (§4.3).
 */
class JourFerieController extends Controller
{
    public function index(Request $request): Response
    {
        $annee = (int) $request->integer('annee', (int) now()->year);

        $jours = JourFerie::query()->orderBy('date_ferie')->get()
            ->map(function (JourFerie $j) use ($annee) {
                // Une fête fixe récurrente s'affiche à sa date de l'année consultée.
                $date = $j->est_recurrent
                    ? Carbon::create($annee, $j->date_ferie->month, $j->date_ferie->day)
                    : $j->date_ferie;

                return [
                    'id' => $j->id,
                    'libelle' => $j->libelle,
                    'libelle_ar' => $j->libelle_ar,
                    'date_ferie' => $j->date_ferie->toDateString(),
                    'date_affichee' => $date->toDateString(),
                    'type' => $j->type->value,
                    'est_recurrent' => $j->est_recurrent,
                    'statut_confirmation' => $j->statut_confirmation->value,
                    'groupe' => $j->groupe,
                ];
            })
            ->filter(fn ($j) => str_starts_with($j['date_affichee'], (string) $annee))
            ->sortBy('date_affichee')
            ->values();

        return Inertia::render('Parametrage/JoursFeries/Index', [
            'jours' => $jours,
            'annee' => $annee,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        JourFerie::create($this->validated($request));

        return back()->with('success', 'ferie.created');
    }

    public function update(Request $request, JourFerie $jourFerie): RedirectResponse
    {
        $jourFerie->update($this->validated($request, $jourFerie));

        return back()->with('success', 'ferie.updated');
    }

    public function destroy(JourFerie $jourFerie): RedirectResponse
    {
        $jourFerie->delete();

        return back()->with('success', 'ferie.deleted');
    }

    /**
     * Après l'annonce officielle : la fête (tous ses jours) passe à « confirmé ».
     */
    public function confirmer(JourFerie $jourFerie): RedirectResponse
    {
        $this->plage($jourFerie)->each->update(['statut_confirmation' => StatutConfirmation::Confirme]);

        return back()->with('success', 'ferie.confirmed');
    }

    /**
     * Décale toute la plage de la fête (ex. les 3 jours de l'Aïd) d'un nombre de jours.
     */
    public function decaler(Request $request, JourFerie $jourFerie): RedirectResponse
    {
        $data = $request->validate(['jours' => ['required', 'integer', 'between:-7,7', 'not_in:0']]);

        DB::transaction(function () use ($jourFerie, $data) {
            // Plus lointain en premier pour ne jamais entrer en collision avec un jour voisin.
            $plage = $this->plage($jourFerie)->sortBy('date_ferie', descending: $data['jours'] > 0);

            foreach ($plage as $jour) {
                $jour->update(['date_ferie' => $jour->date_ferie->copy()->addDays($data['jours'])->toDateString()]);
            }
        });

        return back()->with('success', 'ferie.shifted');
    }

    /**
     * @return Collection<int, JourFerie>
     */
    private function plage(JourFerie $jourFerie)
    {
        return $jourFerie->groupe
            ? JourFerie::where('groupe', $jourFerie->groupe)->get()
            : collect([$jourFerie]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?JourFerie $jour = null): array
    {
        $data = $request->validate([
            'libelle' => ['required', 'string', 'max:255'],
            'libelle_ar' => ['nullable', 'string', 'max:255'],
            'date_ferie' => ['required', 'date_format:Y-m-d', Rule::unique('jours_feries', 'date_ferie')
                ->where('libelle', $request->input('libelle'))->ignore($jour?->id)],
            'type' => ['required', Rule::enum(TypeJourFerie::class)],
            'est_recurrent' => ['required', 'boolean'],
            'statut_confirmation' => ['required', Rule::enum(StatutConfirmation::class)],
        ]);

        return $data;
    }
}
