<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase\ViewInvoice;

use Ramsey\Uuid\UuidInterface;

final readonly class ViewInvoiceRequest
{
    public function __construct(
        public UuidInterface $invoiceId,
    ) {}
}
