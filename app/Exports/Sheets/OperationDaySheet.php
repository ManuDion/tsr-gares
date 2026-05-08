<?php

namespace App\Exports\Sheets;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class OperationDaySheet implements FromArray, WithTitle
{
    public function __construct(
        protected Builder $query,
        protected string $type,
        protected Carbon $date
    ) {
    }

    public function array(): array
    {
        $items = $this->query
            ->whereDate('operation_date', $this->date->toDateString())
            ->get();

        $rows = $items->map(function ($item) {
            if ($this->type === 'recettes') {
                $recetteInter = ($item->ticket_inter_amount ?? 0) + ($item->bagage_inter_amount ?? 0);
                $recetteNational = ($item->ticket_national_amount ?? 0) + ($item->bagage_national_amount ?? 0);

                return [
                    'Date' => optional($item->operation_date)->format('Y-m-d'),
                    'Gare' => $item->gare->name ?? '-',
                    'Tickets inter' => $item->ticket_inter_amount ?? 0,
                    'Tickets national' => $item->ticket_national_amount ?? 0,
                    'Bagages inter' => $item->bagage_inter_amount ?? 0,
                    'Bagages national' => $item->bagage_national_amount ?? 0,
                    'Recette inter' => $recetteInter,
                    'Recette nationale' => $recetteNational,
                    'Montant total' => $item->amount,
                    'Libelle' => $item->description ?? '-',
                ];
            }

            return [
                'Date' => optional($item->operation_date)->format('Y-m-d'),
                'Gare' => $item->gare->name ?? '-',
                'Montant' => $item->amount,
                'Reference' => $item->reference ?? '-',
                'Libelle' => $item->description ?? ($item->motif ?? '-'),
            ];
        })->values()->all();

        $header = $this->type === 'recettes'
            ? ['Date', 'Gare', 'Tickets inter', 'Tickets national', 'Bagages inter', 'Bagages national', 'Recette inter', 'Recette nationale', 'Montant total', 'Libelle']
            : ['Date', 'Gare', 'Montant', 'Reference', 'Libelle'];

        return [
            ['Type', ucfirst($this->type)],
            ['Date', $this->date->format('Y-m-d')],
            [],
            $header,
            ...$rows,
        ];
    }

    public function title(): string
    {
        return $this->date->format('Y-m-d');
    }
}

