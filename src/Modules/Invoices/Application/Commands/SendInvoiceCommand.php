<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Commands;

use Ramsey\Uuid\UuidInterface;

final readonly class SendInvoiceCommand
{
    public function __construct(
        public UuidInterface $invoiceId,
    ) {}
}
