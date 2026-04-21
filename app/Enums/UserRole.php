<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Responsable = 'responsable';

    case ChefDeGare = 'chef_de_gare';
    case CaissierGare = 'caissier_gare';

    case Controleur = 'controleur';

    case AgentCourrierGare = 'agent_courrier_gare';
    case CaissierCourrier = 'caissier_courrier';

    case ResponsableRh = 'responsable_rh';
    case PersonnelTsr = 'personnel_tsr';

    // Legacy support
    case Caissiere = 'caissiere';
    case ChefDeZone = 'chef_de_zone';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Responsable => 'Responsable',
            self::ChefDeGare => 'Chef de gare',
            self::CaissierGare, self::Caissiere, self::ChefDeZone => 'Caissier gare',
            self::Controleur => 'Contrôleur',
            self::AgentCourrierGare => 'Agent courrier gare',
            self::CaissierCourrier => 'Caissier courrier',
            self::ResponsableRh => 'Responsable RH',
            self::PersonnelTsr => 'Personnel TSR',
        };
    }

    public function module(): ?ServiceModule
    {
        return match ($this) {
            self::Admin, self::Responsable => null,
            self::ChefDeGare, self::CaissierGare, self::Caissiere, self::ChefDeZone => ServiceModule::Gares,
            self::Controleur => ServiceModule::Documents,
            self::AgentCourrierGare, self::CaissierCourrier => ServiceModule::Courrier,
            self::ResponsableRh, self::PersonnelTsr => ServiceModule::Rh,
        };
    }

    public function requiresPrimaryGare(): bool
    {
        return in_array($this, [self::ChefDeGare, self::AgentCourrierGare], true);
    }

    public function supportsMultipleGares(): bool
    {
        return in_array($this, [self::CaissierGare, self::Caissiere, self::ChefDeZone, self::CaissierCourrier], true);
    }

    public static function fromLegacyAware(string $value): self
    {
        return match ($value) {
            'caissiere' => self::CaissierGare,
            'chef_de_zone' => self::CaissierGare,
            default => self::from($value),
        };
    }

    public static function options(): array
    {
        $options = [];
        foreach (ServiceModule::cases() as $module) {
            $options[$module->value] = $module->roleOptions();
        }

        return $options;
    }
}
