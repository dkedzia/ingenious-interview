<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Invoices\Presentation\Http\InvoiceController;

Route::get('/invoices/{id}', [InvoiceController::class, 'viewInvoice'])->whereUuid('id')->name('invoices.show');
Route::post('/invoices', [InvoiceController::class, 'createInvoice'])->name('invoices.store');
Route::post('/invoices/{id}/send', [InvoiceController::class, 'sendInvoice'])->whereUuid('id')->name('invoices.send');
