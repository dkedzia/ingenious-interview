<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Console;

use Illuminate\Console\Command;
use Modules\Invoices\Application\Services\InvoiceNotificationDeliveryInterface;
use Throwable;

final class DispatchPendingInvoiceNotifications extends Command
{
    protected $signature = 'invoices:dispatch-pending-notifications';

    protected $description = 'Deliver pending or stale invoice notifications without a queue worker.';

    public function handle(InvoiceNotificationDeliveryInterface $delivery): int
    {
        $failed = false;

        foreach ($delivery->dueInvoiceIds() as $invoiceId) {
            try {
                $delivery->deliver($invoiceId);
            } catch (Throwable $exception) {
                $failed = true;
                $this->error("Failed to deliver invoice {$invoiceId->toString()}: {$exception->getMessage()}");
                report($exception);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
