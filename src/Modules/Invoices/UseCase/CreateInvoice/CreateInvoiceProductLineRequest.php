<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase\CreateInvoice;

final readonly class CreateInvoiceProductLineRequest
{
    public function __construct(
        public string $name,
        public int $quantity,
        public int $unitPrice,
    ) {}
}
