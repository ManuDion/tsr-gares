<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Responsable = 'responsable';
    case ChefDeGare = 'chef_de_gare';
    case Caissiere = 'caissiere';
    case ChefDeZone = 'chef_de_zone'; // Legacy support

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Responsable => 'Responsable',
            self::ChefDeGare => 'Chef de gare',
            self::Caissiere, self::ChefDeZone => 'Caissière',
        };
    }

    public static function options(): array
    {
        return [
            ['value' => self::Admin->value, 'label' => self::Admin->label()],
            ['value' => self::Responsable->value, 'label' => self::Responsable->label()],
            ['value' => self::ChefDeGare->value, 'label' => self::ChefDeGare->label()],
            ['value' => self::Caissiere->value, 'label' => self::Caissiere->label()],
        ];
    }
}
