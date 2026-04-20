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
    ) {}

    public function array(): array
    {
        $items = $this->query
            ->whereDate('operation_date', $this->date->toDateString())
            ->get();

        $rows = $items->map(function ($item) {
            if ($this->type === 'recettes') {
                return [
                    'Date' => optional($item->operation_date)->format('Y-m-d'),
                    'Gare' => $item->gare->name ?? '—',
                    'Tickets inter' => $item->ticket_inter_amount ?? 0,
                    'Tickets national' => $item->ticket_national_amount ?? 0,
                    'Bagages inter' => $item->bagage_inter_amount ?? 0,
                    'Bagages national' => $item->bagage_national_amount ?? 0,
                    'Montant total' => $item->amount,
                    'Libellé' => $item->description ?? '—',
                ];
            }

            return [
                'Date' => optional($item->operation_date)->format('Y-m-d'),
                'Gare' => $item->gare->name ?? '—',
                'Montant' => $item->amount,
                'Référence' => $item->reference ?? '—',
                'Libellé' => $item->description ?? ($item->motif ?? '—'),
            ];
        })->values()->all();

        $header = $this->type === 'recettes'
            ? ['Date', 'Gare', 'Tickets inter', 'Tickets national', 'Bagages inter', 'Bagages national', 'Montant total', 'Libellé']
            : ['Date', 'Gare', 'Montant', 'Référence', 'Libellé'];

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
