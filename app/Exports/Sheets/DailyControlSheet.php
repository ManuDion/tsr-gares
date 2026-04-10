<?php

namespace App\Exports\Sheets;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class DailyControlSheet implements FromArray, WithTitle
{
    public function __construct(
        protected Builder $query,
        protected Carbon $date
    ) {}

    public function array(): array
    {
        $rows = $this->query
            ->whereDate('concerned_date', $this->date->toDateString())
            ->get()
            ->map(function ($item) {
                return [
                    'Gare' => $item->gare->name ?? '—',
                    'Recette' => $item->has_recette ? 'Oui' : 'Non',
                    'Dépense' => $item->has_depense ? 'Oui' : 'Non',
                    'Versement' => $item->has_versement ? 'Oui' : 'Non',
                    'Statut' => $item->is_compliant ? 'Conforme' : 'Anomalie',
                    'Manquants' => implode(', ', $item->missing_operations ?? []),
                ];
            })
            ->values()
            ->all();

        return [
            ['Contrôle concerné', $this->date->format('Y-m-d')],
            [],
            ['Gare', 'Recette', 'Dépense', 'Versement', 'Statut', 'Manquants'],
            ...$rows,
        ];
    }

    public function title(): string
    {
        return $this->date->format('Y-m-d');
    }
}
