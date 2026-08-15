<?php

use App\Http\Controllers\AdvisoryController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetImportController;
use App\Http\Controllers\ExposureController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\IntegrityController;
use App\Http\Controllers\PlaybookController;
use App\Http\Controllers\ProcessAreaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReadinessController;
use App\Http\Controllers\SiteController;
use App\Models\Alert;
use App\Models\Asset;
use App\Models\AssetAdvisoryMatch;
use App\Models\Incident;
use App\Models\ManualFallbackAuthorization;
use App\Models\Organization;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    $drillMonths = Organization::find(app(TenantContext::class)->id())?->fallback_drill_stale_months ?? 12;

    return Inertia::render('Dashboard', [
        'metrics' => [
            'assets' => Asset::count(),
            'exposed' => Asset::where('is_internet_facing', true)->count(),
            'open_alerts' => Alert::where('status', 'open')->count(),
            'critical_alerts' => Alert::where('status', '!=', 'resolved')->where('severity', 'critical')->count(),
            'open_incidents' => Incident::where('status', 'open')->count(),
            'overdue_drills' => ManualFallbackAuthorization::where(fn ($q) => $q->whereNull('last_drilled_at')->orWhere('last_drilled_at', '<', now()->subMonths($drillMonths)))->count(),
            'advisories' => AssetAdvisoryMatch::where('status', 'open')->count(),
        ],
        'recentAlerts' => Alert::where('status', '!=', 'resolved')->latest()->limit(5)->get(),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/assets/import', [AssetImportController::class, 'create'])->name('assets.import.create');
    Route::post('/assets/import/preview', [AssetImportController::class, 'preview'])->name('assets.import.preview');
    Route::post('/assets/import', [AssetImportController::class, 'store'])->name('assets.import.store');
    Route::resource('assets', AssetController::class);
    Route::get('/exposure', [ExposureController::class, 'index'])->name('exposure.index');
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::patch('/alerts/digest', [AlertController::class, 'updateDigest'])->name('alerts.digest.update');
    Route::patch('/alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])->name('alerts.acknowledge');
    Route::patch('/alerts/{alert}/resolve', [AlertController::class, 'resolve'])->name('alerts.resolve');
    Route::post('/assets/{asset}/exposure/manual', [ExposureController::class, 'storeManual'])->name('assets.exposure.manual');
    Route::post('/assets/{asset}/integrity/manual', [IntegrityController::class, 'store'])->name('assets.integrity.manual');
    Route::patch('/integrity-events/{event}/accept', [IntegrityController::class, 'accept'])->name('integrity-events.accept');
    Route::get('/playbooks', [PlaybookController::class, 'index'])->name('playbooks.index');
    Route::post('/playbooks', [PlaybookController::class, 'store'])->name('playbooks.store');
    Route::patch('/playbooks/{playbook}', [PlaybookController::class, 'update'])->name('playbooks.update');
    Route::get('/readiness', [ReadinessController::class, 'index'])->name('readiness.index');
    Route::post('/readiness/authorizations', [ReadinessController::class, 'authorizeOperator'])->name('readiness.authorizations.store');
    Route::patch('/readiness/settings', [ReadinessController::class, 'updateSettings'])->name('readiness.settings.update');
    Route::post('/readiness/authorizations/{authorization}/drills', [ReadinessController::class, 'drill'])->name('readiness.drills.store');
    Route::get('/incidents', [IncidentController::class, 'index'])->name('incidents.index');
    Route::post('/incidents', [IncidentController::class, 'store'])->name('incidents.store');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::post('/incidents/{incident}/actions', [IncidentController::class, 'action'])->name('incidents.actions.store');
    Route::patch('/incidents/{incident}/resolve', [IncidentController::class, 'resolve'])->name('incidents.resolve');
    Route::get('/incidents/{incident}/print', [IncidentController::class, 'print'])->name('incidents.print');
    Route::resource('advisories', AdvisoryController::class)->only(['index', 'create', 'store', 'show']);
    Route::patch('/advisory-matches/{match}', [AdvisoryController::class, 'updateMatch'])->name('advisory-matches.update');
    Route::get('/sites', [SiteController::class, 'index'])->name('sites.index');
    Route::post('/sites', [SiteController::class, 'store'])->name('sites.store');
    Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
    Route::post('/sites/{site}/process-areas', [ProcessAreaController::class, 'store'])->name('process-areas.store');
    Route::delete('/process-areas/{processArea}', [ProcessAreaController::class, 'destroy'])->name('process-areas.destroy');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
