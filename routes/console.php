<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Afficher une citation inspirante');

Schedule::command('gares:run-daily-control')
    ->dailyAt('10:00')
    ->timezone('Africa/Abidjan')
    ->withoutOverlapping();
