<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Services;

use Ramsey\Uuid\UuidInterface;

interface InvoiceNotificationDeliveryInterface
{
    public function deliver(UuidInterface $invoiceId): void;

    /** @return UuidInterface[] */
    public function dueInvoiceIds(): array;
}
