<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdministrativeDocumentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordPersonalizationController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepenseController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GareController;
use App\Http\Controllers\JustificatifController;
use App\Http\Controllers\NotificationHistoryController;
use App\Http\Controllers\PerformanceReportController;
use App\Http\Controllers\RecetteController;
use App\Http\Controllers\Rh\EmployeeController;
use App\Http\Controllers\Rh\EmployeeDocumentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\VersementBancaireController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/mot-de-passe/personnaliser', [PasswordPersonalizationController::class, 'edit'])->name('password.personalize.edit');
    Route::put('/mot-de-passe/personnaliser', [PasswordPersonalizationController::class, 'update'])->name('password.personalize.update');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('password.personalized')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::post('/gares/{gare}/toggle-active', [GareController::class, 'toggleActive'])->name('gares.toggle-active');
        Route::resource('gares', GareController::class);

        Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::resource('users', UserController::class)->except('show');

        Route::resource('recettes', RecetteController::class)->except(['show', 'destroy']);
        Route::post('/recettes/{recette}/unlock', [RecetteController::class, 'unlock'])->name('recettes.unlock');

        Route::post('/depenses/{depense}/unlock', [DepenseController::class, 'unlock'])->name('depenses.unlock');
        Route::resource('depenses', DepenseController::class)->except(['show', 'destroy']);

        Route::post('/versements/{versement}/unlock', [VersementBancaireController::class, 'unlock'])->name('versements.unlock');
        Route::resource('versements', VersementBancaireController::class)->except(['show', 'destroy']);

        Route::get('/verifications', [VerificationController::class, 'index'])->name('verifications.index');
        Route::delete('/verifications/purge-period', [VerificationController::class, 'purgePeriod'])->name('verifications.purge-period');
        Route::post('/verifications/{verification}/confirm', [VerificationController::class, 'confirm'])->name('verifications.confirm');
        Route::post('/verifications/{verification}/enable-adjustments', [VerificationController::class, 'enableAdjustments'])->name('verifications.enable-adjustments');

        Route::get('/chat', [ConversationController::class, 'index'])->name('chat.index');
        Route::post('/chat', [ConversationController::class, 'store'])->name('chat.store');
        Route::get('/chat/{conversation}', [ConversationController::class, 'show'])->name('chat.show');
        Route::post('/chat/{conversation}/messages', [ConversationController::class, 'storeMessage'])->name('chat.messages.store');

        Route::get('/documents-administratifs', [AdministrativeDocumentController::class, 'index'])->name('administrative-documents.index');
        Route::get('/documents-administratifs/create', [AdministrativeDocumentController::class, 'create'])->name('administrative-documents.create');
        Route::post('/documents-administratifs', [AdministrativeDocumentController::class, 'store'])->name('administrative-documents.store');
        Route::get('/documents-administratifs/{administrativeDocument}/edit', [AdministrativeDocumentController::class, 'edit'])->name('administrative-documents.edit');
        Route::put('/documents-administratifs/{administrativeDocument}', [AdministrativeDocumentController::class, 'update'])->name('administrative-documents.update');
        Route::delete('/documents-administratifs/{administrativeDocument}', [AdministrativeDocumentController::class, 'destroy'])->name('administrative-documents.destroy');
        Route::get('/documents-administratifs/{administrativeDocument}/preview', [AdministrativeDocumentController::class, 'preview'])->name('administrative-documents.preview');
        Route::get('/documents-administratifs/{administrativeDocument}/download', [AdministrativeDocumentController::class, 'download'])->name('administrative-documents.download');

        Route::prefix('rh')->name('rh.')->group(function () {
            Route::resource('employees', EmployeeController::class)->except(['destroy']);
            Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('employees.documents.store');
            Route::delete('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('employees.documents.destroy');
        });

        Route::get('/notifications', [NotificationHistoryController::class, 'index'])->name('notifications.index');
        Route::delete('/notifications/purge-period', [NotificationHistoryController::class, 'purgePeriod'])->name('notifications.purge-period');
        Route::get('/rapports/performance', PerformanceReportController::class)->name('reports.performance');
        Route::get('/historique-systeme', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('/historique-systeme/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
        Route::delete('/historique-systeme/{activityLog}', [ActivityLogController::class, 'destroy'])->name('activity-logs.destroy');

        Route::get('/exports/recettes', [ExportController::class, 'recettes'])->name('exports.recettes');
        Route::get('/exports/depenses', [ExportController::class, 'depenses'])->name('exports.depenses');
        Route::get('/exports/controls', [ExportController::class, 'controls'])->name('exports.controls');

        Route::get('/justificatifs/{piece}/preview', [JustificatifController::class, 'preview'])->name('justificatifs.preview');
        Route::get('/justificatifs/{piece}/download', [JustificatifController::class, 'download'])->name('justificatifs.download');
    });
});

Route::view('/offline', 'offline')->name('offline');
