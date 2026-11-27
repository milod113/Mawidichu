<?php

namespace Database\Seeders;

use App\Enums\CodeConsultation;
use App\Enums\Grade;
use App\Enums\Priorite;
use App\Enums\RoleName;
use App\Enums\StatutAvis;
use App\Enums\StatutRdv;
use App\Enums\TypeAvis;
use App\Models\Box;
use App\Models\Consultation;
use App\Models\ConstantePreconsultation;
use App\Models\DemandeAvis;
use App\Models\ExceptionPlanning;
use App\Models\Medecin;
use App\Models\Patient;
use App\Models\PlanningType;
use App\Models\RendezVous;
use App\Models\Service;
use App\Models\User;
use App\Services\IppGenerator;
use App\Services\TicketNumberer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Jeu de démonstration (local / testing uniquement, jamais en production) :
 * patients fictifs avec IPP, rendez-vous dans tous les statuts (passés, du jour, à venir),
 * consultations avec ordonnance, constantes, demandes d'avis et une absence planifiée.
 * Idempotent : ne fait rien si des patients existent déjà.
 */
class DemoPatientsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || Patient::exists()) {
            return;
        }

        $domain = config('mawid.seed_email_domain');
        $cardio = Service::where('code', 'CARDIO')->first();
        $pedia = Service::firstOrCreate(['code' => 'DERMA'], ['nom' => 'Dermatologie', 'nom_ar' => 'الأمراض الجلدية', 'nb_box' => 1, 'aile' => 'Aile B']);
        $secretaire = User::where('email', 'secretaire@'.$domain)->first();
        $infirmier = User::where('email', 'infirmier@'.$domain)->first();
        if (! $cardio || ! $secretaire || ! $infirmier) {
            return; // DemoDataSeeder / TestUserSeeder doivent passer d'abord
        }

        $medCardio = Medecin::where('service_id', $cardio->id)->where('grade', Grade::MCA)->first();
        $resident = Medecin::where('service_id', $cardio->id)->where('grade', Grade::Resident)->first();
        $chef = Medecin::where('service_id', $cardio->id)->where('grade', Grade::Pr)->first();
        $medPedia = $this->dermatologue($pedia);
        $infirmier->update(['affectation_service_id' => $cardio->id]);

        $this->complementerPlannings($cardio, $pedia, $medCardio, $chef, $medPedia);

        // Patients
        $noms = [
            ['Benali', 'Sara', 'F'], ['Zerrouki', 'Amine', 'M'], ['Hamdi', 'Nour', 'F'], ['Mebarki', 'Yacine', 'M'], ['Kaci', 'Lina', 'F'],
            ['Boumediene', 'Karim', 'M'], ['Cherif', 'Amel', 'F'], ['Belkacem', 'Rachid', 'M'], ['Slimani', 'Fatima', 'F'], ['Meziane', 'Walid', 'M'],
            ['Ouali', 'Samira', 'F'], ['Lahmar', 'Tarek', 'M'], ['Brahimi', 'Meriem', 'F'], ['Haddad', 'Ismail', 'M'], ['Khelifi', 'Djamila', 'F'],
            ['Taleb', 'Mourad', 'M'], ['Bensaid', 'Houria', 'F'], ['Guerrab', 'Sofiane', 'M'], ['Amrani', 'Ghania', 'F'], ['Rahmani', 'Abdelkader', 'M'],
            ['Djebbar', 'Yasmine', 'F'], ['Mansouri', 'Nabil', 'M'], ['Berrabah', 'Imane', 'F'], ['Chaoui', 'Anis', 'M'], ['Dahmani', 'Zineb', 'F'],
            ['Fellah', 'Hocine', 'M'], ['Gherbi', 'Malika', 'F'], ['Hadjadj', 'Bilal', 'M'], ['Kadi', 'Soumia', 'F'], ['Larbi', 'Redouane', 'M'],
        ];
        $ipp = app(IppGenerator::class);
        $wilayas = ['Tlemcen', 'Tlemcen', 'Tlemcen', 'Sidi Bel Abbès', 'Aïn Témouchent', 'Naâma', 'Oran', 'Béchar'];
        $patients = collect($noms)->map(fn ($n, $i) => Patient::create([
            'ipp' => $ipp->next(), 'nom' => $n[0], 'prenom' => $n[1], 'sexe' => $n[2],
            'date_naissance' => now()->subYears(25 + ($i * 7) % 55)->subDays($i * 13)->toDateString(),
            'telephone' => '055'.str_pad((string) (1000000 + $i * 7919), 7, '0', STR_PAD_LEFT),
            'wilaya' => $wilayas[$i % count($wilayas)], 'commune' => 'Centre',
        ]));

        $typesCardio = $cardio->typesConsultation->keyBy(fn ($t) => $t->code->value);
        $typesPedia = $pedia->typesConsultation->keyBy(fn ($t) => $t->code->value);
        $num = app(TicketNumberer::class);
        $i = 0;

        $rdv = function (Service $s, Medecin $m, string $typeCode, string $date, string $heure, StatutRdv $statut, array $extra = []) use (&$i, $patients, $secretaire, $typesCardio, $typesPedia, $num, $cardio) {
            $type = ($s->id === $cardio->id ? $typesCardio : $typesPedia)[$typeCode];

            return RendezVous::create($extra + [
                'numero_ticket' => $num->next($s, $date), 'patient_id' => $patients[$i++ % $patients->count()]->id,
                'service_id' => $s->id, 'medecin_id' => $m->id, 'box_id' => $m->planningsTypes()->value('box_id'),
                'type_consultation_id' => $type->id, 'date_rdv' => $date, 'heure_vague_prevue' => $heure.':00',
                'statut' => $statut, 'created_by' => $secretaire->id,
            ]);
        };

        $aujourdhui = today()->toDateString();
        $now = now();

        // Passé : consultations terminées (avec dossier), absents, reportés
        $terminees = [];
        foreach ([[-21, 'premiere_consultation', 'HTA grade 1', 'Amlodipine 5 mg|1 cp/j|30 j'], [-14, 'premiere_consultation', 'Angor stable', 'Bisoprolol 5 mg|1 cp/j|30 j'],
            [-10, 'controle', 'HTA équilibrée', 'Amlodipine 5 mg|1 cp/j|60 j'], [-7, 'premiere_consultation', 'Insuffisance cardiaque', 'Furosémide 40 mg|1 cp/j|15 j'],
            [-5, 'retour_bilan', 'Dyslipidémie', 'Atorvastatine 20 mg|1 cp/soir|90 j'], [-3, 'controle', 'Fibrillation atriale', 'Apixaban 5 mg|2 cp/j|90 j']] as $k => [$jours, $code, $diag, $med]) {
            $m = $k % 2 ? $resident : $medCardio;
            $r = $rdv($cardio, $m, $code, today()->addDays($jours)->toDateString(), sprintf('%02d:30', 8 + $k % 3), StatutRdv::Termine, [
                'heure_arrivee_checkin' => today()->addDays($jours)->setTime(8, 20), 'heure_appel' => today()->addDays($jours)->setTime(8, 20 + 10 + $k * 4),
            ]);
            [$nom, $poso, $duree] = explode('|', $med);
            $terminees[] = Consultation::create([
                'rdv_id' => $r->id, 'patient_id' => $r->patient_id, 'service_id' => $cardio->id, 'medecin_id' => $m->id,
                'superviseur_senior_id' => $m->id === $resident->id ? $medCardio->id : null,
                'motif_reel' => 'Douleurs thoraciques / suivi', 'examen_clinique' => 'Examen cardio-vasculaire sans particularité notable.',
                'diagnostic' => $diag, 'conduite_a_tenir' => 'Traitement médical, surveillance tensionnelle, régime hyposodé.',
                'ordonnance_json' => [['medicament' => $nom, 'posologie' => $poso, 'duree' => $duree]],
                'bilans_demandes_json' => ['NFS', 'Bilan lipidique', 'ECG'], 'delai_controle_jours' => $k % 2 ? 30 : null,
            ]);
        }
        $rdv($cardio, $medCardio, 'controle', today()->subDays(2)->toDateString(), '09:00', StatutRdv::Absent);
        $rdv($cardio, $resident, 'premiere_consultation', today()->subDays(4)->toDateString(), '09:30', StatutRdv::Absent);
        $rdv($cardio, $medCardio, 'premiere_consultation', today()->subDays(6)->toDateString(), '10:00', StatutRdv::Reporte);
        Patient::whereIn('id', RendezVous::where('statut', StatutRdv::Absent)->pluck('patient_id'))->increment('nb_absences');
        $rdv($pedia, $medPedia, 'premiere_consultation', today()->subDays(8)->toDateString(), '09:00', StatutRdv::Termine);

        // Aujourd'hui : un patient dans chaque état du parcours
        $jour = [
            [$medCardio, 'controle', '08:30', StatutRdv::Planifie, []],
            [$medCardio, 'premiere_consultation', '08:30', StatutRdv::Arrive, ['heure_arrivee_checkin' => $now->copy()->subMinutes(35)]],
            [$resident, 'premiere_consultation', '09:00', StatutRdv::EnPreconsultation, ['heure_arrivee_checkin' => $now->copy()->subMinutes(30)]],
            [$medCardio, 'controle', '09:00', StatutRdv::PretMedecin, ['heure_arrivee_checkin' => $now->copy()->subMinutes(25), 'priorite' => Priorite::PersonneAgeeEnceinte, 'motif_priorite' => 'Patient de 78 ans']],
            [$medCardio, 'retour_bilan', '09:30', StatutRdv::PretMedecin, ['heure_arrivee_checkin' => $now->copy()->subMinutes(20), 'priorite' => Priorite::HorsWilaya, 'motif_priorite' => 'Vient de Béchar']],
            [$medCardio, 'controle', '10:00', StatutRdv::Appele, ['heure_arrivee_checkin' => $now->copy()->subMinutes(40), 'heure_appel' => $now->copy()->subMinutes(3)]],
            [$chef, 'premiere_consultation', '10:00', StatutRdv::EnConsultation, ['heure_arrivee_checkin' => $now->copy()->subMinutes(60), 'heure_appel' => $now->copy()->subMinutes(15)]],
            [$medCardio, 'controle', '10:30', StatutRdv::EnAttenteRetour, ['heure_arrivee_checkin' => $now->copy()->subMinutes(50), 'heure_appel' => $now->copy()->subMinutes(10)]],
            [$resident, 'controle', '11:00', StatutRdv::Planifie, ['priorite' => Priorite::PersonnelChu, 'motif_priorite' => 'Agent du CHU']],
            [$medCardio, 'premiere_consultation', '11:00', StatutRdv::Termine, ['heure_arrivee_checkin' => $now->copy()->subHours(3), 'heure_appel' => $now->copy()->subHours(3)->addMinutes(12)]],
        ];
        $duJour = [];
        foreach ($jour as [$m, $code, $h, $statut, $extra]) {
            $duJour[] = $rdv($cardio, $m, $code, $aujourdhui, $h, $statut, $extra);
        }
        foreach ($duJour as $r) {
            if (in_array($r->statut, [StatutRdv::PretMedecin, StatutRdv::Appele, StatutRdv::EnConsultation, StatutRdv::Termine], true)) {
                ConstantePreconsultation::create([
                    'rdv_id' => $r->id, 'infirmier_id' => $infirmier->id, 'tension_sys' => 120 + $r->id % 30, 'tension_dia' => 70 + $r->id % 15,
                    'frequence_cardiaque' => 62 + $r->id % 25, 'poids' => 60 + $r->id % 30, 'taille' => 160 + $r->id % 25, 'temperature' => 36.8, 'glycemie' => 0.95,
                    'notes_ecg_soins' => 'ECG de repos réalisé.',
                ]);
            }
        }
        $enConsultation = $duJour[6];
        $consultEnCours = Consultation::create([
            'rdv_id' => $enConsultation->id, 'patient_id' => $enConsultation->patient_id, 'service_id' => $cardio->id, 'medecin_id' => $chef->id,
            'motif_reel' => 'Palpitations', 'examen_clinique' => 'En cours…',
        ]);
        $rdv($pedia, $medPedia, 'premiere_consultation', $aujourdhui, '09:00', StatutRdv::Arrive, ['heure_arrivee_checkin' => $now->copy()->subMinutes(15)]);
        $rdv($pedia, $medPedia, 'controle', $aujourdhui, '09:30', StatutRdv::Planifie);

        // À venir : prochaines vacations et patients à replanifier
        foreach ([1, 2, 3, 8, 9, 10] as $k => $plus) {
            $rdv($cardio, $k % 2 ? $resident : $medCardio, $k % 3 ? 'premiere_consultation' : 'controle', today()->addDays($plus)->toDateString(), sprintf('%02d:00', 8 + $k % 3), StatutRdv::Planifie);
        }
        $rdv($cardio, $medCardio, 'controle', today()->addDays(5)->toDateString(), '09:00', StatutRdv::AReplanifier);
        $rdv($pedia, $medPedia, 'premiere_consultation', today()->addDays(4)->toDateString(), '10:00', StatutRdv::AReplanifier);

        // Absence planifiée (congrès) et contrôle à planifier (délai noté, pas encore de RDV)
        ExceptionPlanning::create(['service_id' => $cardio->id, 'medecin_id' => $chef->id, 'date_debut' => today()->addDays(14)->toDateString(),
            'date_fin' => today()->addDays(16)->toDateString(), 'motif' => 'Congrès de cardiologie']);

        // Demandes d'avis : senior ↔ interne, et inter-spécialités (accès temporaire)
        DemandeAvis::create([
            'consultation_id' => $terminees[1]->id, 'patient_id' => $terminees[1]->patient_id, 'service_demandeur_id' => $cardio->id,
            'medecin_demandeur_id' => $resident->id, 'service_cible_id' => $cardio->id, 'medecin_cible_id' => $medCardio->id,
            'type_avis' => TypeAvis::SeniorInterne, 'motif' => 'Confirmer l\'indication du traitement.', 'reponse_avis' => 'Traitement validé.', 'statut' => StatutAvis::Repondu,
        ]);
        DemandeAvis::create([
            'consultation_id' => $consultEnCours->id, 'patient_id' => $consultEnCours->patient_id, 'service_demandeur_id' => $cardio->id,
            'medecin_demandeur_id' => $chef->id, 'service_cible_id' => $pedia->id,
            'type_avis' => TypeAvis::InterSpecialite, 'motif' => 'Avis dermatologique avant prise en charge.', 'statut' => StatutAvis::EnAttente,
            'expire_le' => now()->addDays(config('mawid.avis_validite_jours')),
        ]);
    }

    private function dermatologue(Service $pedia): Medecin
    {
        $domain = config('mawid.seed_email_domain');
        $user = User::firstOrCreate(['email' => 'dermato@'.$domain], [
            'name' => 'Dr. Dermatologue', 'password' => Hash::make(config('mawid.seed_password')), 'email_verified_at' => now(), 'locale' => 'fr',
        ]);
        $user->syncRoles([RoleName::Medecin->value]);

        return Medecin::firstOrCreate(['user_id' => $user->id], ['service_id' => $pedia->id, 'grade' => Grade::MCB, 'specialite' => 'Dermatologie']);
    }

    private function complementerPlannings(Service $cardio, Service $pedia, Medecin $medCardio, Medecin $chef, Medecin $medPedia): void
    {
        $box1 = Box::where('service_id', $cardio->id)->where('numero_box', 1)->first();
        $box2 = Box::where('service_id', $cardio->id)->where('numero_box', 2)->first();
        $boxPedia = Box::firstOrCreate(['service_id' => $pedia->id, 'numero_box' => 1], ['nom_affichage' => 'Box Dermatologie']);
        foreach (CodeConsultation::cases() as $c) {
            $pedia->typesConsultation()->firstOrCreate(['code' => $c->value], [
                'libelle' => ['premiere_consultation' => 'Première consultation', 'controle' => 'Contrôle', 'retour_bilan' => 'Retour bilan'][$c->value],
                'duree_minutes' => $c === CodeConsultation::PremiereConsultation ? 20 : 10, 'couleur' => '#7c3aed',
            ]);
        }

        // Cardiologie : lundi, mercredi et jeudi en plus de la vacation du mardi (DemoDataSeeder)
        foreach ([[1, $medCardio, $box1], [3, $medCardio, $box1], [4, $chef, $box2]] as [$jour, $m, $box]) {
            PlanningType::firstOrCreate(['service_id' => $cardio->id, 'medecin_id' => $m->id, 'jour_semaine' => $jour], [
                'box_id' => $box?->id, 'heure_debut' => '08:30', 'heure_fin' => '12:00', 'quota_rdv' => 12, 'taille_vague' => 6, 'intervalle_vague_min' => 60,
            ]);
        }
        foreach ([1, 2, 3, 4] as $jour) {
            PlanningType::firstOrCreate(['service_id' => $pedia->id, 'medecin_id' => $medPedia->id, 'jour_semaine' => $jour], [
                'box_id' => $boxPedia->id, 'heure_debut' => '09:00', 'heure_fin' => '12:00', 'quota_rdv' => 10, 'taille_vague' => 5, 'intervalle_vague_min' => 60,
            ]);
        }
    }
}
