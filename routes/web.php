<?php

use App\Http\Controllers\AgreementController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\PaymentController;
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

    // Payment capture, staging, detail and reversal (TASK-007, FR-03, FR-04)
    Route::get('partners/{partner}/agreements/{agreement}/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('partners/{partner}/agreements/{agreement}/payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('partners/{partner}/agreements/{agreement}/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('partners/{partner}/agreements/{agreement}/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('partners/{partner}/agreements/{agreement}/payments/{payment}/allocations/{allocation}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
