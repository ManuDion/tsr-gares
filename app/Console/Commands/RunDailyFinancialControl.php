<?php

namespace App\Console\Commands;

use App\Services\DailyControlService;
use Illuminate\Console\Command;

class RunDailyFinancialControl extends Command
{
    protected $signature = 'gares:run-daily-control {--date=}';

    protected $description = 'Contrôle journalier des recettes, dépenses et versements par gare';

    public function __construct(protected DailyControlService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $concernedDate = $this->option('date')
            ? now()->parse($this->option('date'))->toDateString()
            : now('Africa/Abidjan')->subDay()->toDateString();

        $this->service->runForDate($concernedDate);

        $this->info('Contrôle journalier exécuté pour '.$concernedDate);

        return self::SUCCESS;
    }
}
