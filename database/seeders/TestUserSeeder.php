<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Comptes de démonstration, un par rôle (plus un chef de service qui
 * cumule le rôle de médecin). Réservé aux environnements local / testing.
 */
class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $domain = config('mawid.seed_email_domain');
        $password = Hash::make(config('mawid.seed_password'));

        $comptes = [
            ['admin', 'Administrateur Système', [RoleName::SuperAdmin]],
            ['direction', 'Direction de la Clinique', [RoleName::Direction]],
            ['chef.service', 'Pr. Chef de Service', [RoleName::ChefService, RoleName::Medecin]],
            ['medecin', 'Dr. Médecin Consultant', [RoleName::Medecin]],
            ['resident', 'Dr. Médecin Résident', [RoleName::Medecin]],
            ['infirmier', 'Infirmier Pré-consultation', [RoleName::Infirmier]],
            ['secretaire', 'Secrétaire Médicale', [RoleName::Secretaire]],
            ['ecran.tv', 'Écran TV Salle d\'attente', [RoleName::EcranTv]],
        ];

        foreach ($comptes as [$login, $name, $roles]) {
            $user = User::withTrashed()->updateOrCreate(
                ['email' => $login.'@'.$domain],
                [
                    'name' => $name,
                    'password' => $password,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            if ($user->trashed()) {
                $user->restore();
            }

            $user->syncRoles(array_map(fn (RoleName $r) => $r->value, $roles));
        }
    }
}
