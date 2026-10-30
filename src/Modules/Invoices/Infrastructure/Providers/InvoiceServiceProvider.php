<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Application\Listeners\MarkInvoiceAsSentToClientListener;
use Modules\Invoices\Application\Outbox\InvoiceNotificationOutboxInterface;
use Modules\Invoices\Application\Services\InvoiceNotificationDelivery;
use Modules\Invoices\Application\Services\InvoiceNotificationDeliveryInterface;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Infrastructure\Console\DispatchPendingInvoiceNotifications;
use Modules\Invoices\Infrastructure\Repositories\EloquentInvoiceNotificationOutbox;
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
        $this->app->scoped(
            InvoiceNotificationOutboxInterface::class,
            EloquentInvoiceNotificationOutbox::class,
        );
        $this->app->bind(
            InvoiceNotificationDeliveryInterface::class,
            InvoiceNotificationDelivery::class,
        );
    }

    public function boot(): void
    {
        $this->commands([
            DispatchPendingInvoiceNotifications::class,
        ]);

        Event::listen(
            WebhookDeliveredEvent::class,
            MarkInvoiceAsSentToClientListener::class,
        );

        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('src/Modules/Invoices/Presentation/routes.php'));
    }
}
