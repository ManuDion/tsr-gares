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
                $ticketInter = $this->toIntegerAmount($item->ticket_inter_amount ?? 0);
                $ticketNational = $this->toIntegerAmount($item->ticket_national_amount ?? 0);
                $bagageInter = $this->toIntegerAmount($item->bagage_inter_amount ?? 0);
                $bagageNational = $this->toIntegerAmount($item->bagage_national_amount ?? 0);
                $recetteInter = $ticketInter + $bagageInter;
                $recetteNational = $ticketNational + $bagageNational;

                return [
                    'Date' => optional($item->operation_date)->format('Y-m-d'),
                    'Gare' => $item->gare->name ?? '-',
                    'Tickets inter' => $ticketInter,
                    'Tickets national' => $ticketNational,
                    'Bagages inter' => $bagageInter,
                    'Bagages national' => $bagageNational,
                    'Recette inter' => $recetteInter,
                    'Recette nationale' => $recetteNational,
                    'Montant total' => $this->toIntegerAmount($item->amount),
                    'Libelle' => $item->description ?? '-',
                ];
            }

            return [
                'Date' => optional($item->operation_date)->format('Y-m-d'),
                'Gare' => $item->gare->name ?? '-',
                'Montant' => $this->toIntegerAmount($item->amount),
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

    protected function toIntegerAmount(mixed $value): int
    {
        return (int) round((float) $value, 0);
    }
}
