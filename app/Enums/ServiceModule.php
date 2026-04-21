<?php

namespace App\Enums;

use App\Models\Department;

enum ServiceModule: string
{
    case Gares = 'gares';
    case Documents = 'documents';
    case Courrier = 'courrier';
    case Rh = 'rh';

    public function label(): string
    {
        return match ($this) {
            self::Gares => 'Service de gestion des gares',
            self::Documents => 'Service de gestion des documents',
            self::Courrier => 'Service courrier',
            self::Rh => 'Service RH',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Gares => 'Gares',
            self::Documents => 'Documents',
            self::Courrier => 'Courrier',
            self::Rh => 'RH',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Gares => 'Recettes, dépenses, versements, vérifications et supervision des gares.',
            self::Documents => 'Suivi des documents administratifs, échéances et conformité.',
            self::Courrier => 'Service courrier exploité dans les gares avec la même logique métier que le module gares.',
            self::Rh => 'Préparation du module Ressources Humaines pour le cycle administratif du personnel.',
        };
    }

    public function departmentCode(): string
    {
        return match ($this) {
            self::Gares => 'GARES',
            self::Documents => 'DOCS',
            self::Courrier => 'COURRIER',
            self::Rh => 'RH',
        };
    }

    public function financialScope(): ?string
    {
        return match ($this) {
            self::Gares => 'gares',
            self::Courrier => 'courrier',
            default => null,
        };
    }

    public function supportsFinancialFlows(): bool
    {
        return in_array($this, [self::Gares, self::Courrier], true);
    }

    public function roleOptions(): array
    {
        $universal = [
            ['value' => UserRole::Admin->value, 'label' => UserRole::Admin->label()],
            ['value' => UserRole::Responsable->value, 'label' => UserRole::Responsable->label()],
        ];

        $specific = match ($this) {
            self::Gares => [
                ['value' => UserRole::ChefDeGare->value, 'label' => UserRole::ChefDeGare->label()],
                ['value' => UserRole::CaissierGare->value, 'label' => UserRole::CaissierGare->label()],
            ],
            self::Documents => [
                ['value' => UserRole::Controleur->value, 'label' => UserRole::Controleur->label()],
            ],
            self::Courrier => [
                ['value' => UserRole::AgentCourrierGare->value, 'label' => UserRole::AgentCourrierGare->label()],
                ['value' => UserRole::CaissierCourrier->value, 'label' => UserRole::CaissierCourrier->label()],
            ],
            self::Rh => [
                ['value' => UserRole::ResponsableRh->value, 'label' => UserRole::ResponsableRh->label()],
                ['value' => UserRole::PersonnelTsr->value, 'label' => UserRole::PersonnelTsr->label()],
            ],
        };

        return [...$universal, ...$specific];
    }

    public static function options(): array
    {
        return array_map(fn (self $module) => [
            'value' => $module->value,
            'label' => $module->label(),
            'short_label' => $module->shortLabel(),
            'description' => $module->description(),
        ], self::cases());
    }

    public static function fromDepartment(?Department $department): ?self
    {
        return self::fromDepartmentCode($department?->code);
    }

    public static function fromDepartmentCode(?string $code): ?self
    {
        return match (strtoupper((string) $code)) {
            'GARES', 'EXP', 'FIN', 'DIR' => self::Gares,
            'DOCS', 'DOC', 'ADM', 'CTL' => self::Documents,
            'COURRIER', 'CRR' => self::Courrier,
            'RH' => self::Rh,
            default => null,
        };
    }
}
