<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepenseController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GareController;
use App\Http\Controllers\JustificatifController;
use App\Http\Controllers\NotificationHistoryController;
use App\Http\Controllers\PerformanceReportController;
use App\Http\Controllers\RecetteController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VersementBancaireController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::post('/gares/{gare}/toggle-active', [GareController::class, 'toggleActive'])->name('gares.toggle-active');
    Route::resource('gares', GareController::class);

    Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::resource('users', UserController::class)->except('show');

    Route::resource('recettes', RecetteController::class)->except(['show', 'destroy']);
    Route::post('/recettes/{recette}/unlock', [RecetteController::class, 'unlock'])->name('recettes.unlock');

    Route::resource('depenses', DepenseController::class)->except(['show', 'edit', 'update', 'destroy']);

    Route::post('/versements/analyze', [VersementBancaireController::class, 'analyze'])->name('versements.analyze');
    Route::post('/versements/{versement}/unlock', [VersementBancaireController::class, 'unlock'])->name('versements.unlock');
    Route::resource('versements', VersementBancaireController::class)->except(['show', 'destroy']);

    Route::get('/notifications', [NotificationHistoryController::class, 'index'])->name('notifications.index');
    Route::get('/rapports/performance', PerformanceReportController::class)->name('reports.performance');

    Route::get('/exports/recettes', [ExportController::class, 'recettes'])->name('exports.recettes');
    Route::get('/exports/depenses', [ExportController::class, 'depenses'])->name('exports.depenses');
    Route::get('/exports/controls', [ExportController::class, 'controls'])->name('exports.controls');

    Route::get('/justificatifs/{piece}/preview', [JustificatifController::class, 'preview'])->name('justificatifs.preview');
    Route::get('/justificatifs/{piece}/download', [JustificatifController::class, 'download'])->name('justificatifs.download');
});

Route::view('/offline', 'offline')->name('offline');
