<?php

namespace App\Enums;

/**
 * Les 7 rôles RBAC de Mawid CHU (cahier des charges, section 2).
 *
 * Un utilisateur peut cumuler plusieurs rôles (ex. chef_service + medecin).
 * Les permissions fines seront rattachées à ces rôles au fil des modules.
 */
enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case Direction = 'direction';
    case ChefService = 'chef_service';
    case Medecin = 'medecin';
    case Infirmier = 'infirmier';
    case Secretaire = 'secretaire';
    case EcranTv = 'ecran_tv';

    /**
     * Clé de traduction du libellé (lang/ui/{fr,ar}.json).
     */
    public function labelKey(): string
    {
        return 'roles.'.$this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
