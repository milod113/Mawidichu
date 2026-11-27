<?php

// Messages de validation les plus courants ; les autres retombent sur la
// langue de secours. À compléter au fil des modules.
return [
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'current_password' => 'Le mot de passe est incorrect.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'in' => 'La valeur du champ :attribute est invalide.',
    'lowercase' => 'Le champ :attribute doit être en minuscules.',
    'max' => ['string' => 'Le champ :attribute ne doit pas dépasser :max caractères.'],
    'min' => ['string' => 'Le champ :attribute doit contenir au moins :min caractères.'],
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',
    'unique' => 'La valeur du champ :attribute est déjà utilisée.',
    'password' => [
        'letters' => 'Le champ :attribute doit contenir au moins une lettre.',
        'mixed' => 'Le champ :attribute doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le champ :attribute doit contenir au moins un chiffre.',
        'symbols' => 'Le champ :attribute doit contenir au moins un symbole.',
        'uncompromised' => 'Ce :attribute est apparu dans une fuite de données. Choisissez-en un autre.',
    ],
    'attributes' => [
        'name' => 'nom',
        'email' => 'adresse e-mail',
        'password' => 'mot de passe',
        'current_password' => 'mot de passe actuel',
        'telephone' => 'téléphone',
        'locale' => 'langue',
    ],
];
