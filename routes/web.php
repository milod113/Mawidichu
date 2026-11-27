<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Parametrage\BoxController;
use App\Http\Controllers\Parametrage\JourFerieController;
use App\Http\Controllers\Parametrage\MedecinController;
use App\Http\Controllers\Parametrage\PlanningTypeController;
use App\Http\Controllers\Parametrage\ServiceController;
use App\Http\Controllers\Parametrage\TypeConsultationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard');

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

/*
| Paramétrage : super administrateur (tout) et chef de service (son service).
| Les droits fins sont portés par ServicePolicy.
*/
Route::middleware(['auth', 'verified', 'role:super_admin|chef_service'])
    ->prefix('parametrage')->name('parametrage.')->group(function () {
        Route::resource('services', ServiceController::class)->except(['edit']);

        Route::prefix('services/{service}')->name('services.')->group(function () {
            Route::resource('boxes', BoxController::class)->only(['store', 'update', 'destroy']);
            Route::resource('types-consultation', TypeConsultationController::class)
                ->parameters(['types-consultation' => 'typeConsultation'])->only(['store', 'update', 'destroy']);
            Route::resource('medecins', MedecinController::class)->only(['store', 'update', 'destroy']);
            Route::resource('plannings', PlanningTypeController::class)
                ->parameters(['plannings' => 'planningType'])->only(['store', 'update', 'destroy']);
        });
    });

Route::middleware(['auth', 'verified', 'role:super_admin'])
    ->prefix('parametrage/jours-feries')->name('parametrage.jours-feries.')->group(function () {
        Route::get('/', [JourFerieController::class, 'index'])->name('index');
        Route::post('/', [JourFerieController::class, 'store'])->name('store');
        Route::put('{jourFerie}', [JourFerieController::class, 'update'])->name('update');
        Route::delete('{jourFerie}', [JourFerieController::class, 'destroy'])->name('destroy');
        Route::post('{jourFerie}/confirmer', [JourFerieController::class, 'confirmer'])->name('confirmer');
        Route::post('{jourFerie}/decaler', [JourFerieController::class, 'decaler'])->name('decaler');
    });

require __DIR__.'/auth.php';
