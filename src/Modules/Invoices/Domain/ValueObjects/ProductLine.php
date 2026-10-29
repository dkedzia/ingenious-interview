<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\ValueObjects;

use Modules\Invoices\Domain\Exceptions\InvalidProductLineException;

final readonly class ProductLine
{
    public function __construct(
        public string $name,
        public int $quantity,
        public int $unitPrice,
    ) {
        if ($this->quantity <= 0) {
            throw InvalidProductLineException::nonPositiveQuantity($this->quantity);
        }

        if ($this->unitPrice <= 0) {
            throw InvalidProductLineException::nonPositiveUnitPrice($this->unitPrice);
        }
    }

    public function totalUnitPrice(): int
    {
        return $this->quantity * $this->unitPrice;
    }
}
