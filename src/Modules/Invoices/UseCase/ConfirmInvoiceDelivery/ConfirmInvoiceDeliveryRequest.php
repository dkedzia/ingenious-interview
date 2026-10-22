<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase\ConfirmInvoiceDelivery;

use Ramsey\Uuid\UuidInterface;

final readonly class ConfirmInvoiceDeliveryRequest
{
    public function __construct(
        public UuidInterface $invoiceId,
    ) {}
}
