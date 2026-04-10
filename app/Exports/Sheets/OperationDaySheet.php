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
        $rows = $this->query
            ->whereDate('operation_date', $this->date->toDateString())
            ->get()
            ->map(function ($item) {
                return [
                    'Date' => optional($item->operation_date)->format('Y-m-d'),
                    'Gare' => $item->gare->name ?? '—',
                    'Montant' => $item->amount,
                    'Référence' => $item->reference ?? '—',
                    'Libellé' => $item->description ?? ($item->motif ?? '—'),
                ];
            })
            ->values()
            ->all();

        return [
            ['Type', ucfirst($this->type)],
            ['Date', $this->date->format('Y-m-d')],
            [],
            ['Date', 'Gare', 'Montant', 'Référence', 'Libellé'],
            ...$rows,
        ];
    }

    public function title(): string
    {
        return $this->date->format('Y-m-d');
    }
}
