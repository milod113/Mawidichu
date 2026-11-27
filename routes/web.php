<?php

use App\Http\Controllers\Accueil\FileController;
use App\Http\Controllers\Accueil\PatientController;
use App\Http\Controllers\Dossier\DocumentController;
use App\Http\Controllers\Dossier\DossierController;
use App\Http\Controllers\Infirmier\PreconsultationController;
use App\Http\Controllers\Medecin\AvisController;
use App\Http\Controllers\Medecin\ConsultationController;
use App\Http\Controllers\Medecin\FileMedecinController;
use App\Http\Controllers\Medecin\PdfController;
use App\Http\Controllers\Stats\StatsController;
use App\Http\Controllers\Tv\TvController;
use App\Http\Controllers\Parametrage\ExceptionPlanningController;
use App\Http\Controllers\Accueil\RendezVousController;
use App\Http\Controllers\Accueil\TicketController;
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
    // Le compte kiosque de la salle d'attente n'a qu'un écran.
    if (auth()->user()->hasRole('ecran_tv')) {
        return redirect()->route('tv');
    }

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

/*
| Accueil : patients (IPP) et prise de rendez-vous. Lecture des patients ouverte
| aux médecins ; création / modification et prise de RDV réservées à l'accueil.
*/
Route::middleware(['auth', 'verified'])->prefix('accueil')->name('accueil.')->group(function () {
    Route::middleware('role:secretaire|super_admin|medecin|chef_service')->group(function () {
        Route::get('patients', [PatientController::class, 'index'])->name('patients.index');
    });

    Route::middleware('role:secretaire|super_admin')->group(function () {
        Route::post('patients', [PatientController::class, 'store'])->name('patients.store');
        Route::put('patients/{patient}', [PatientController::class, 'update'])->name('patients.update');

        Route::get('rendez-vous/nouveau', [RendezVousController::class, 'create'])->name('rendez-vous.create');
        Route::post('rendez-vous', [RendezVousController::class, 'store'])->name('rendez-vous.store');
        Route::post('rendez-vous/verrou', [RendezVousController::class, 'verrou'])->name('rendez-vous.verrou');
        Route::delete('rendez-vous/verrou', [RendezVousController::class, 'liberer'])->name('rendez-vous.liberer');
        Route::delete('rendez-vous/{rendezVous}', [RendezVousController::class, 'destroy'])->name('rendez-vous.destroy');
    });

    Route::middleware('role:secretaire|super_admin|medecin|chef_service')->group(function () {
        Route::get('patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
        Route::get('rendez-vous/{rendezVous}', [RendezVousController::class, 'show'])->name('rendez-vous.show');
        Route::get('rendez-vous/{rendezVous}/ticket/{format}', [TicketController::class, 'show'])->name('rendez-vous.ticket');
    });
});

/*
| File du jour : pointage, priorités, absents (accueil) et pré-consultation (infirmier).
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::middleware('role:secretaire|super_admin|chef_service|medecin|infirmier')->get('accueil/jour', [FileController::class, 'index'])->name('accueil.jour');

    Route::middleware('role:secretaire|super_admin|chef_service')->prefix('accueil/rendez-vous/{rendezVous}')->name('accueil.rendez-vous.')->group(function () {
        Route::post('arrivee', [FileController::class, 'checkIn'])->name('arrivee');
        Route::post('absent', [FileController::class, 'absent'])->name('absent');
        Route::post('priorite', [FileController::class, 'priorite'])->name('priorite');
    });

    Route::middleware('role:infirmier|super_admin')->prefix('infirmier/preconsultation')->name('infirmier.preconsultation.')->group(function () {
        Route::get('/', [PreconsultationController::class, 'index'])->name('index');
        Route::post('{rendezVous}/demarrer', [PreconsultationController::class, 'demarrer'])->name('demarrer');
        Route::post('{rendezVous}/constantes', [PreconsultationController::class, 'store'])->name('store');
    });

    Route::middleware('role:super_admin|chef_service')->prefix('parametrage/absences')->name('parametrage.absences.')->group(function () {
        Route::get('/', [ExceptionPlanningController::class, 'index'])->name('index');
        Route::post('/', [ExceptionPlanningController::class, 'store'])->name('store');
        Route::post('{absence}/appliquer', [ExceptionPlanningController::class, 'appliquer'])->name('appliquer');
        Route::delete('{absence}', [ExceptionPlanningController::class, 'destroy'])->name('destroy');
    });
});

/*
| Médecin : file d'attente, appels, consultation, demandes d'avis, documents PDF.
*/
Route::middleware(['auth', 'verified', 'role:medecin|chef_service'])->prefix('medecin')->name('medecin.')->group(function () {
    Route::get('file', [FileMedecinController::class, 'index'])->name('file');
    Route::post('file/suivant', [FileMedecinController::class, 'suivant'])->name('suivant');
    Route::post('rendez-vous/{rendezVous}/appeler', [FileMedecinController::class, 'appeler'])->name('appeler');
    Route::post('rendez-vous/{rendezVous}/rappeler', [FileMedecinController::class, 'rappeler'])->name('rappeler');
    Route::post('rendez-vous/{rendezVous}/absent', [FileMedecinController::class, 'absent'])->name('absent');
    Route::post('rendez-vous/{rendezVous}/demarrer', [FileMedecinController::class, 'demarrer'])->name('demarrer');

    Route::get('consultations/{consultation}', [ConsultationController::class, 'show'])->name('consultations.show');
    Route::put('consultations/{consultation}', [ConsultationController::class, 'update'])->name('consultations.update');
    Route::get('consultations/{consultation}/disponibilites', [ConsultationController::class, 'disponibilites'])->name('consultations.disponibilites');
    Route::get('consultations/{consultation}/pdf/{type}', [PdfController::class, 'consultation'])->name('consultations.pdf');
    Route::post('consultations/{consultation}/avis', [AvisController::class, 'store'])->name('avis.store');
    Route::get('avis', [AvisController::class, 'index'])->name('avis.index');
    Route::put('avis/{avis}', [AvisController::class, 'repondre'])->name('avis.repondre');
    Route::put('favoris', [ConsultationController::class, 'favoris'])->name('favoris');
});

/*
| Dossier médical cloisonné et pièces jointes.
*/
Route::middleware(['auth', 'verified'])->prefix('dossier')->name('dossier.')->group(function () {
    Route::get('patients/{patient}', [DossierController::class, 'show'])->middleware('role:medecin|chef_service|super_admin')->name('show');
    Route::middleware('role:secretaire|medecin|chef_service|super_admin')->group(function () {
        Route::post('patients/{patient}/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('documents/{document}', [DocumentController::class, 'download'])->name('documents.download');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    });
});

Route::middleware(['auth', 'verified', 'role:secretaire|super_admin|chef_service|medecin'])
    ->get('accueil/rendez-vous/{rendezVous}/certificat', [PdfController::class, 'certificat'])->name('accueil.rendez-vous.certificat');

/*
| Statistiques : direction (tout), chef de service (son service), super admin.
*/
Route::middleware(['auth', 'verified', 'role:direction|chef_service|super_admin'])->prefix('statistiques')->name('stats.')->group(function () {
    Route::get('/', [StatsController::class, 'index'])->name('index');
    Route::get('pdf', [StatsController::class, 'pdf'])->name('pdf');
    Route::get('excel', [StatsController::class, 'excel'])->name('excel');
});

/*
| Écran TV de la salle d'attente.
*/
Route::middleware(['auth', 'role:ecran_tv|super_admin'])->group(function () {
    Route::get('tv', [TvController::class, 'show'])->name('tv');
    Route::get('tv/donnees', [TvController::class, 'donnees'])->name('tv.donnees');
});

require __DIR__.'/auth.php';
