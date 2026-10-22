<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Providers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Infrastructure\Listeners\ConfirmInvoiceDeliveryOnWebhookDelivered;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;

/**
 * Not deferred, unlike InvoiceServiceProvider: a deferred provider is loaded
 * only once one of its bindings is resolved, so its listeners would never be
 * registered by the time the Notifications module dispatches the event.
 */
final class InvoiceEventServiceProvider extends ServiceProvider
{
    public function boot(Dispatcher $events): void
    {
        $events->listen(WebhookDeliveredEvent::class, ConfirmInvoiceDeliveryOnWebhookDelivered::class);
    }
}
