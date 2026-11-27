<?php

/*
|--------------------------------------------------------------------------
| Mawid CHU — paramètres généraux de l'application
|--------------------------------------------------------------------------
|
| Tout ce qui dépend de l'établissement passe par ce fichier (et donc par
| le .env) : rien n'est figé dans le code. Les paramètres métier fins
| (services, médecins, vacations, quotas…) seront stockés en base de
| données à partir de l'Étape 2.
|
*/

return [

    'etablissement' => [
        'nom' => env('MAWID_ETABLISSEMENT_NOM', 'Clinique Boudghène'),
        'nom_ar' => env('MAWID_ETABLISSEMENT_NOM_AR', 'عيادة بودغن'),
        'rattachement' => env('MAWID_ETABLISSEMENT_RATTACHEMENT', 'CHU Dr. Tidjani Damerdji — Tlemcen'),
        'rattachement_ar' => env('MAWID_ETABLISSEMENT_RATTACHEMENT_AR', 'المركز الاستشفائي الجامعي د. تيجاني دمرجي — تلمسان'),
    ],

    /*
    | Langues proposées dans l'interface. "dir" pilote l'attribut HTML dir
    | (ltr / rtl) ; l'arabe bascule toute la mise en page en RTL.
    */
    'locales' => [
        'fr' => ['label' => 'Français', 'dir' => 'ltr'],
        'ar' => ['label' => 'العربية', 'dir' => 'rtl'],
    ],

    /*
    | Thème par défaut tant que l'utilisateur n'a rien choisi :
    | "light", "dark" ou "system" (suit le réglage du poste).
    */
    'default_theme' => env('MAWID_DEFAULT_THEME', 'system'),

    /*
    | Mot de passe des comptes de test créés par les seeders. Ces comptes
    | ne sont créés qu'en environnement local / testing (voir
    | DatabaseSeeder). À changer avant toute démonstration.
    */
    'seed_password' => env('MAWID_SEED_PASSWORD', 'password'),

    /*
    | Domaine des adresses e-mail des comptes de test.
    */
    'seed_email_domain' => env('MAWID_SEED_EMAIL_DOMAIN', 'mawid.local'),

];
