<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Amorce la table `jours_feries` à partir de database/data/jours_feries_algerie.php.
 * Idempotent : relancer le seeder ne crée pas de doublons et n'écrase pas
 * un statut déjà confirmé par l'administrateur.
 */
class JourFerieSeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('data/jours_feries_algerie.php');
        $now = now();

        foreach ($data['fixes'] as $fete) {
            $this->upsert([
                'libelle' => $fete['libelle'],
                'libelle_ar' => $fete['libelle_ar'],
                'date_ferie' => $data['annee_reference_fixes'].'-'.$fete['mois_jour'],
                'type' => 'fixe',
                'est_recurrent' => true,
                'statut_confirmation' => 'confirme',
            ], $now);
        }

        foreach ($data['mobiles'] as $fetes) {
            foreach ($fetes as [$libelle, $libelleAr, $premierJour, $nbJours]) {
                $debut = CarbonImmutable::parse($premierJour);

                for ($i = 0; $i < $nbJours; $i++) {
                    $suffixe = $nbJours > 1 ? ' ('.($i + 1).'/'.$nbJours.')' : '';

                    $this->upsert([
                        'libelle' => $libelle.$suffixe,
                        'libelle_ar' => $libelleAr.$suffixe,
                        'date_ferie' => $debut->addDays($i)->toDateString(),
                        'type' => 'mobile',
                        'est_recurrent' => false,
                        'statut_confirmation' => 'estimatif',
                        'groupe' => Str::slug($libelle).'-'.$debut->year,
                    ], $now);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsert(array $row, \DateTimeInterface $now): void
    {
        $key = ['libelle' => $row['libelle'], 'date_ferie' => $row['date_ferie']];

        if (DB::table('jours_feries')->where($key)->exists()) {
            // Données déjà présentes : on complète seulement le groupe s'il manque.
            if (isset($row['groupe'])) {
                DB::table('jours_feries')->where($key)->whereNull('groupe')->update(['groupe' => $row['groupe']]);
            }

            return;
        }

        DB::table('jours_feries')->insert([...$row, 'created_at' => $now, 'updated_at' => $now]);
    }
}
