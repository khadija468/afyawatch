<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\DashboardDataController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FhirController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// Public, indexable pages
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/summary', [PageController::class, 'summary'])->name('summary');
Route::get('/robots.txt', [PageController::class, 'robots']);
Route::get('/sitemap.xml', [PageController::class, 'sitemap']);

// Everything below is private and must never appear in search results
Route::middleware(['guest', 'noindex'])->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
});

Route::middleware(['auth', 'noindex'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/account', [AccountController::class, 'edit'])->name('account');
    Route::put('/account', [AccountController::class, 'update']);

    Route::prefix('api')->group(function () {
        Route::get('/dashboard', DashboardDataController::class);
        Route::get('/export.csv', ExportController::class)->name('export');
        Route::post('/alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge']);
        Route::get('/fhir/MeasureReport', [FhirController::class, 'measureReport']);
    });
});
