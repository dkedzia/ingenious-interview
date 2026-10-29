<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\DTOs;

final readonly class ProductLineData
{
    public function __construct(
        public string $name,
        public int $quantity,
        public int $unitPrice,
    ) {}
}
