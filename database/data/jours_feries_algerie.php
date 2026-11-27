<?php

/*
|--------------------------------------------------------------------------
| Jours fériés légaux en Algérie — données initiales
|--------------------------------------------------------------------------
|
| Ces lignes ne servent qu'à amorcer la table `jours_feries`, qui reste
| entièrement administrable (ajout, correction, décalage d'un jour après
| l'annonce officielle).
|
| - Fêtes fixes : récurrentes chaque année au même jour/mois (la date
|   indique la première année de référence).
| - Fêtes religieuses (calendrier lunaire) : dates ESTIMATIVES calculées
|   à l'avance ; elles doivent être confirmées ou décalées par
|   l'administrateur après l'observation du croissant (nuit du doute).
|
*/

$fixes = [
    ['libelle' => 'Nouvel An', 'libelle_ar' => 'رأس السنة الميلادية', 'mois_jour' => '01-01'],
    ['libelle' => 'Yennayer (Nouvel An amazigh)', 'libelle_ar' => 'يناير (رأس السنة الأمازيغية)', 'mois_jour' => '01-12'],
    ['libelle' => 'Fête du Travail', 'libelle_ar' => 'عيد العمال', 'mois_jour' => '05-01'],
    ['libelle' => "Fête de l'Indépendance", 'libelle_ar' => 'عيد الاستقلال', 'mois_jour' => '07-05'],
    ['libelle' => 'Anniversaire du déclenchement de la Révolution', 'libelle_ar' => 'عيد اندلاع الثورة', 'mois_jour' => '11-01'],
];

// Fêtes mobiles : [libellé, libellé arabe, date du 1er jour, nombre de jours]
$mobiles = [
    2026 => [
        ['Aïd el-Fitr', 'عيد الفطر', '2026-03-20', 3],
        ['Aïd el-Adha', 'عيد الأضحى', '2026-05-27', 3],
        ['Awal Muharram', 'أول محرم', '2026-06-16', 1],
        ['Achoura', 'عاشوراء', '2026-06-25', 1],
        ['Mawlid Ennabaoui', 'المولد النبوي الشريف', '2026-08-25', 1],
    ],
    2027 => [
        ['Aïd el-Fitr', 'عيد الفطر', '2027-03-09', 3],
        ['Aïd el-Adha', 'عيد الأضحى', '2027-05-16', 3],
        ['Awal Muharram', 'أول محرم', '2027-06-06', 1],
        ['Achoura', 'عاشوراء', '2027-06-15', 1],
        ['Mawlid Ennabaoui', 'المولد النبوي الشريف', '2027-08-14', 1],
    ],
];

return [
    'annee_reference_fixes' => 2026,
    'fixes' => $fixes,
    'mobiles' => $mobiles,
];
