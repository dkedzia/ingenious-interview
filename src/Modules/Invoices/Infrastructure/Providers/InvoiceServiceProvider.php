<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Application\Listeners\MarkInvoiceAsSentToClientListener;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Infrastructure\Repositories\EloquentInvoiceRepository;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;

final class InvoiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(
            InvoiceRepositoryInterface::class,
            EloquentInvoiceRepository::class,
        );
    }

    public function boot(): void
    {
        Event::listen(
            WebhookDeliveredEvent::class,
            MarkInvoiceAsSentToClientListener::class,
        );
    }
}
