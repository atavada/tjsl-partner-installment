<?php

use App\Http\Controllers\AgreementController;
use App\Http\Controllers\PartnerController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('partners', [PartnerController::class, 'index'])->name('partners.index');
    Route::get('partners/{partner}', [PartnerController::class, 'show'])->name('partners.show');
    Route::get('partners/{partner}/agreements', [AgreementController::class, 'index'])->name('agreements.index');
    Route::get('partners/{partner}/agreements/{agreement}', [AgreementController::class, 'show'])->name('agreements.show');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
