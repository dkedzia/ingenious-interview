<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase\CreateInvoice;

final readonly class CreateInvoiceRequest
{
    /** @param list<CreateInvoiceProductLineRequest> $productLines */
    public function __construct(
        public string $customerName,
        public string $customerEmail,
        public array $productLines = [],
    ) {}
}
