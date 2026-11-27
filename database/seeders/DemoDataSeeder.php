<?php

namespace Database\Seeders;

use App\Enums\CodeConsultation;
use App\Enums\Grade;
use App\Models\Box;
use App\Models\Medecin;
use App\Models\PlanningType;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration (local / testing) : deux services, leurs médecins,
 * box, types de consultation et une vacation type. Tout reste modifiable
 * depuis l'écran de paramétrage ; rien de ceci n'est utilisé comme valeur
 * par défaut du code métier.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $domain = config('mawid.seed_email_domain');

        $cardio = Service::firstOrCreate(['code' => 'CARDIO'], [
            'nom' => 'Cardiologie', 'nom_ar' => 'أمراض القلب',
            'preconsultation_active' => true, 'nb_box' => 2,
            'documents_defaut_json' => ['ECG récent', 'Bilan biologique', 'Lettre d\'orientation'],
        ]);
        Service::firstOrCreate(['code' => 'PEDIA'], [
            'nom' => 'Pédiatrie', 'nom_ar' => 'طب الأطفال', 'nb_box' => 1,
        ]);

        $profils = [
            ['chef.service', Grade::Pr, 'Cardiologie'],
            ['medecin', Grade::MCA, 'Cardiologie'],
            ['resident', Grade::Resident, 'Cardiologie'],
        ];

        $medecins = [];
        foreach ($profils as [$login, $grade, $specialite]) {
            $user = User::where('email', $login.'@'.$domain)->first();
            if ($user) {
                $medecins[$login] = Medecin::firstOrCreate(['user_id' => $user->id], [
                    'service_id' => $cardio->id, 'grade' => $grade, 'specialite' => $specialite,
                ]);
            }
        }

        foreach ([1 => 'Box 1 - Seniors', 2 => 'Box 2 - Équipe Résidents'] as $numero => $nom) {
            Box::firstOrCreate(['service_id' => $cardio->id, 'numero_box' => $numero], ['nom_affichage' => $nom]);
        }

        foreach ([
            [CodeConsultation::PremiereConsultation, 'Première consultation', 20, '#0d9488'],
            [CodeConsultation::Controle, 'Contrôle', 10, '#2563eb'],
            [CodeConsultation::RetourBilan, 'Retour bilan', 5, '#d97706'],
        ] as [$code, $libelle, $duree, $couleur]) {
            $cardio->typesConsultation()->firstOrCreate(['code' => $code->value], [
                'libelle' => $libelle, 'duree_minutes' => $duree, 'couleur' => $couleur,
            ]);
        }

        if (isset($medecins['chef.service']) && ! PlanningType::where('service_id', $cardio->id)->exists()) {
            PlanningType::create([
                'service_id' => $cardio->id, 'medecin_id' => $medecins['chef.service']->id,
                'jour_semaine' => 2, 'heure_debut' => '08:30', 'heure_fin' => '11:30',
                'quota_rdv' => 20, 'taille_vague' => 10, 'intervalle_vague_min' => 60,
            ]);
        }
    }
}
