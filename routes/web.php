<?php

use App\Http\Controllers\AgreementController;
use App\Http\Controllers\AgreementDocumentController;
use App\Http\Controllers\FundLotController;
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
    Route::get('agreements/create', [AgreementController::class, 'create'])->name('agreements.create');
    Route::post('agreements', [AgreementController::class, 'store'])->name('agreements.store');
    Route::get('partners/{partner}/agreements/create', [AgreementController::class, 'createForPartner'])->name('partners.agreements.create');
    Route::get('partners/{partner}/agreements', [AgreementController::class, 'index'])->name('agreements.index');
    Route::get('partners/{partner}/agreements/{agreement}', [AgreementController::class, 'show'])->name('agreements.show');
    Route::get('partners/{partner}/agreements/{agreement}/restructure', [AgreementController::class, 'restructure'])->name('agreements.restructure');
    Route::post('partners/{partner}/agreements/{agreement}/restructure', [AgreementController::class, 'processRestructure'])->name('agreements.process-restructure');

    // Agreement Documents (Upload, Download, Signed URLs)
    Route::post('agreements/{agreement}/documents', [AgreementDocumentController::class, 'store'])->name('agreements.documents.store');
    Route::get('agreements/{agreement}/documents/{document}/download', [AgreementDocumentController::class, 'download'])->name('agreements.documents.download');
    Route::get('agreements/{agreement}/documents/{document}/signed-url', [AgreementDocumentController::class, 'signedUrl'])->name('agreements.documents.signed-url');

    // Payment capture, staging, detail and reversal (TASK-007, FR-03, FR-04)
    Route::get('partners/{partner}/agreements/{agreement}/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('partners/{partner}/agreements/{agreement}/payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('partners/{partner}/agreements/{agreement}/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('partners/{partner}/agreements/{agreement}/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('partners/{partner}/agreements/{agreement}/payments/{payment}/allocations/{allocation}/post', [PaymentController::class, 'post'])->name('payments.post');
    Route::post('partners/{partner}/agreements/{agreement}/payments/{payment}/allocations/{allocation}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');

    // Fund lots: ABT capture, listing, identification, and allocation per DEC-006 (FIMPL-008, TASK-REM-007)
    Route::get('abt', [FundLotController::class, 'index'])->name('abt.index');
    Route::get('fund-lots', [FundLotController::class, 'index'])->name('fund-lots.index');
    Route::post('fund-lots/abt', [FundLotController::class, 'storeAbt'])->name('fund-lots.store-abt');
    Route::get('fund-lots/{fundLot}', [FundLotController::class, 'show'])->name('fund-lots.show');
    Route::post('fund-lots/{fundLot}/identify', [FundLotController::class, 'identify'])->name('fund-lots.identify');
    Route::post('fund-lots/{fundLot}/allocate', [FundLotController::class, 'allocateToAgreement'])->name('fund-lots.allocate');
});

// Secure signed document download (temporary signed link access)
Route::get('agreements/{agreement}/documents/{document}/signed-download', [AgreementDocumentController::class, 'signedDownload'])
    ->name('agreements.documents.signed-download')
    ->middleware('signed');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
