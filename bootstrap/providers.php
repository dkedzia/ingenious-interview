<?php

use Modules\Invoices\Infrastructure\Providers\InvoiceEventServiceProvider;
use Modules\Invoices\Infrastructure\Providers\InvoiceServiceProvider;
use Modules\Notifications\Infrastructure\Providers\NotificationServiceProvider;

return [
    InvoiceEventServiceProvider::class,
    InvoiceServiceProvider::class,
    NotificationServiceProvider::class,
];
