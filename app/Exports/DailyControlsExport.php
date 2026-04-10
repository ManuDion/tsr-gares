<?php

namespace App\Exports;

use App\Exports\Sheets\DailyControlSheet;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DailyControlsExport implements WithMultipleSheets
{
    public function __construct(
        protected Builder $baseQuery,
        protected ?Carbon $startDate,
        protected ?Carbon $endDate
    ) {}

    public function sheets(): array
    {
        $start = $this->startDate ?: now()->startOfMonth();
        $end = $this->endDate ?: now()->endOfDay();
        $dates = [];

        foreach ($start->daysUntil($end->copy()->addDay()) as $date) {
            $dates[] = new DailyControlSheet(clone $this->baseQuery, $date);
        }

        return $dates;
    }
}
