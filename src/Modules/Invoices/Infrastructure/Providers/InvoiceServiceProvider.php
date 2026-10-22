<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Domain\Repositories\InvoiceRepository;
use Modules\Invoices\Domain\Services\InvoiceIdGenerator;
use Modules\Invoices\Infrastructure\Notifications\NotificationsCustomerNotifier;
use Modules\Invoices\Infrastructure\Persistence\EloquentInvoiceRepository;
use Modules\Invoices\Infrastructure\Services\UuidV7InvoiceIdGenerator;
use Modules\Invoices\Infrastructure\Validation\LaravelCreateInvoiceRequestValidator;
use Modules\Invoices\UseCase\CreateInvoice\CreateInvoiceRequestValidator;
use Modules\Invoices\UseCase\SendInvoice\CustomerNotifier;

final class InvoiceServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->scoped(InvoiceRepository::class, EloquentInvoiceRepository::class);
        $this->app->scoped(InvoiceIdGenerator::class, UuidV7InvoiceIdGenerator::class);
        $this->app->scoped(CreateInvoiceRequestValidator::class, LaravelCreateInvoiceRequestValidator::class);
        $this->app->scoped(CustomerNotifier::class, NotificationsCustomerNotifier::class);
    }

    /** @return array<class-string> */
    public function provides(): array
    {
        return [
            InvoiceRepository::class,
            InvoiceIdGenerator::class,
            CreateInvoiceRequestValidator::class,
            CustomerNotifier::class,
        ];
    }
}
