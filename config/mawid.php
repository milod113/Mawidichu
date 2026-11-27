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

    /*
    | Numérotation. IPP : {prefix}-{année}-{séquence sur ipp_pad chiffres}.
    | Ticket : {code du service}-{séquence du jour sur ticket_pad chiffres}.
    */
    'ipp_prefix' => env('MAWID_IPP_PREFIX', 'IPP'),
    'ipp_pad' => (int) env('MAWID_IPP_PAD', 6),
    'ticket_pad' => (int) env('MAWID_TICKET_PAD', 3),

    /*
    | Réservation temporaire d'une place pendant la prise de rendez-vous (secondes).
    */
    'verrou_secondes' => (int) env('MAWID_VERROU_SECONDES', 60),

    /*
    | Statuts qui libèrent la place (le RDV n'occupe plus la vacation).
    */
    // Horizon (jours) de recherche de la prochaine disponibilité.
    'horizon_jours' => (int) env('MAWID_HORIZON_JOURS', 180),

    'statuts_liberant_place' => ['reporte', 'a_replanifier'],

    /*
    | Durée de validité (jours) de l'accès temporaire accordé par une demande d'avis
    | inter-spécialités.
    */
    'avis_validite_jours' => (int) env('MAWID_AVIS_VALIDITE_JOURS', 7),

    /*
    | File d'attente : rang des badges de priorité (plus grand = passe avant).
    | Par défaut tous à 0 : l'ordre réel est l'heure de check-in. À ajuster selon
    | la politique de l'établissement.
    */
    'queue_priority_rank' => [
        'normal' => 0,
        'personnel_chu' => 0,
        'orientation_inter_services' => 0,
        'hors_wilaya' => 0,
        'personne_agee_enceinte' => 0,
    ],

    /*
    | Pièces jointes patients : taille max (Ko) et extensions acceptées.
    */
    'upload_max_kb' => (int) env('MAWID_UPLOAD_MAX_KB', 10240),
    'upload_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],

    /*
    | Demandes de bilan : examens proposés en cases à cocher (saisie libre possible en plus).
    */
    'bilans_courants' => [
        'NFS', 'Glycémie à jeun', 'HbA1c', 'Urée / Créatinine', 'Bilan lipidique',
        'Bilan hépatique', 'CRP', 'Ionogramme sanguin', 'ECG', 'Échographie',
        'Radiographie thoracique', 'Écho-Doppler',
    ],

    /*
    | Écran TV : nombre d'appels récents affichés et rafraîchissement de secours (secondes).
    */
    'tv_appels_affiches' => (int) env('MAWID_TV_APPELS', 6),
    'tv_refresh_secondes' => (int) env('MAWID_TV_REFRESH', 30),

];
