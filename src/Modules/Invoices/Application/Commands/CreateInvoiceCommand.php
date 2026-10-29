<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Commands;

use Modules\Invoices\Application\DTOs\ProductLineData;

final readonly class CreateInvoiceCommand
{
    /** @param ProductLineData[] $productLines */
    public function __construct(
        public string $customerName,
        public string $customerEmail,
        public array $productLines = [],
    ) {}
}
