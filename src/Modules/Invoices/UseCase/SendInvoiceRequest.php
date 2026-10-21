<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase;

use Ramsey\Uuid\UuidInterface;

final readonly class SendInvoiceRequest
{
    public function __construct(
        public UuidInterface $invoiceId,
    ) {}
}
