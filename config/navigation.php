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

];
