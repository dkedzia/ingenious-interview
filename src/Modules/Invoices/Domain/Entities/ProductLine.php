<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Entities;

use Modules\Invoices\Domain\Exceptions\InvalidProductLineException;

final readonly class ProductLine
{
    /** @throws InvalidProductLineException */
    public function __construct(
        public string $name,
        public int $quantity,
        public int $unitPrice,
    ) {
        if (trim($name) === '') {
            throw InvalidProductLineException::blankName();
        }

        if ($quantity <= 0) {
            throw InvalidProductLineException::nonPositiveQuantity($quantity);
        }

        if ($unitPrice <= 0) {
            throw InvalidProductLineException::nonPositiveUnitPrice($unitPrice);
        }
    }

    public function totalUnitPrice(): int
    {
        return $this->quantity * $this->unitPrice;
    }
}
