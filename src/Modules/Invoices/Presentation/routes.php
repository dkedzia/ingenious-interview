<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Invoices\Presentation\Http\InvoiceController;
use Ramsey\Uuid\Validator\GenericValidator;

Route::pattern('invoiceId', (new GenericValidator())->getPattern());

Route::prefix('invoices')->group(function (): void {
    Route::get('{invoiceId}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::post('{invoiceId}/send', [InvoiceController::class, 'send'])->name('invoices.send');
});
