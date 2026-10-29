<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Infrastructure\Repositories\EloquentInvoiceRepository;

final class InvoiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(
            InvoiceRepositoryInterface::class,
            EloquentInvoiceRepository::class,
        );
    }
}
