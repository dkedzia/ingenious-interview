<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\Services\InvoiceIdGeneratorInterface;
use Modules\Invoices\Infrastructure\Persistence\EloquentInvoiceRepository;
use Modules\Invoices\Infrastructure\Services\UuidV7InvoiceIdGenerator;
use Modules\Invoices\Infrastructure\Validation\LaravelCreateInvoiceRequestValidator;
use Modules\Invoices\UseCase\CreateInvoiceRequestValidatorInterface;

final class InvoiceServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->scoped(InvoiceRepositoryInterface::class, EloquentInvoiceRepository::class);
        $this->app->scoped(InvoiceIdGeneratorInterface::class, UuidV7InvoiceIdGenerator::class);
        $this->app->scoped(CreateInvoiceRequestValidatorInterface::class, LaravelCreateInvoiceRequestValidator::class);
    }

    /** @return array<class-string> */
    public function provides(): array
    {
        return [
            InvoiceRepositoryInterface::class,
            InvoiceIdGeneratorInterface::class,
            CreateInvoiceRequestValidatorInterface::class,
        ];
    }
}
