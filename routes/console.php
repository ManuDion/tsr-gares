<?php

use App\Http\Controllers\ConversationController;
use App\Services\DocumentExpiryService;
use App\Services\VerificationService;
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

Schedule::call(function (VerificationService $service) {
    $service->runForDate(now('Africa/Abidjan')->subDay()->toDateString());
})
    ->name('verification:daily-balance-check')
    ->dailyAt('10:15')
    ->timezone('Africa/Abidjan')
    ->withoutOverlapping();

Schedule::call(function (DocumentExpiryService $service) {
    $service->ensureFreshAlerts();
})
    ->name('documents:expiry-alerts')
    ->dailyAt('08:00')
    ->timezone('Africa/Abidjan')
    ->withoutOverlapping();

Schedule::call(function (ConversationController $controller) {
    $controller->pruneInactiveConversations();
})
    ->name('chat:prune-inactive-conversations')
    ->dailyAt('03:00')
    ->timezone('Africa/Abidjan')
    ->withoutOverlapping();
