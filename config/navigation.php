<?php

/*
|--------------------------------------------------------------------------
| Menu latéral (sidebar)
|--------------------------------------------------------------------------
|
| Chaque entrée déclare la route Laravel ciblée, la clé de traduction de
| son libellé, une icône et les rôles autorisés (null = tous les
| utilisateurs connectés). Une entrée dont la route n'existe pas encore
| est simplement masquée : les modules des étapes suivantes n'auront qu'à
| ajouter leur ligne ici.
|
*/

return [

    [
        'section' => 'nav.section.general',
        'items' => [
            ['route' => 'dashboard', 'label' => 'nav.dashboard', 'icon' => 'home', 'roles' => null],
            ['route' => 'profile.edit', 'label' => 'nav.profile', 'icon' => 'user', 'roles' => null],
        ],
    ],

    [
        'section' => 'nav.section.accueil',
        'items' => [
            ['route' => 'accueil.patients.index', 'label' => 'nav.patients', 'icon' => 'users', 'roles' => ['secretaire', 'super_admin', 'medecin', 'chef_service']],
            ['route' => 'accueil.jour', 'label' => 'nav.jour', 'icon' => 'clipboard', 'roles' => ['secretaire', 'super_admin', 'chef_service', 'medecin', 'infirmier']],
            ['route' => 'infirmier.preconsultation.index', 'label' => 'nav.preconsultation', 'icon' => 'heart', 'roles' => ['infirmier', 'super_admin']],
            ['route' => 'accueil.rendez-vous.create', 'label' => 'nav.rdv_nouveau', 'icon' => 'plus', 'roles' => ['secretaire', 'super_admin']],
        ],
    ],

    [
        'section' => 'nav.section.parametrage',
        'items' => [
            ['route' => 'parametrage.services.index', 'label' => 'nav.services', 'icon' => 'building', 'roles' => ['super_admin', 'chef_service']],
            ['route' => 'parametrage.absences.index', 'label' => 'nav.absences', 'icon' => 'calendar', 'roles' => ['super_admin', 'chef_service']],
            ['route' => 'parametrage.jours-feries.index', 'label' => 'nav.jours_feries', 'icon' => 'calendar', 'roles' => ['super_admin']],
        ],
    ],

];
