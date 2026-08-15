<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetImportController;
use App\Http\Controllers\ExposureController;
use App\Http\Controllers\ProcessAreaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiteController;
use App\Models\Alert;
use App\Models\Asset;
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
    return Inertia::render('Dashboard', [
        'metrics' => [
            'assets' => Asset::count(),
            'exposed' => Asset::where('is_internet_facing', true)->count(),
            'open_alerts' => Alert::where('status', 'open')->count(),
        ],
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/assets/import', [AssetImportController::class, 'create'])->name('assets.import.create');
    Route::post('/assets/import/preview', [AssetImportController::class, 'preview'])->name('assets.import.preview');
    Route::post('/assets/import', [AssetImportController::class, 'store'])->name('assets.import.store');
    Route::resource('assets', AssetController::class);
    Route::get('/exposure', [ExposureController::class, 'index'])->name('exposure.index');
    Route::post('/assets/{asset}/exposure/manual', [ExposureController::class, 'storeManual'])->name('assets.exposure.manual');
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
