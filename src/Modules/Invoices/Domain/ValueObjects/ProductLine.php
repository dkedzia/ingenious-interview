<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\ValueObjects;

use Modules\Invoices\Domain\Exceptions\InvalidProductLineException;

final readonly class ProductLine
{
    public string $name;

    public function __construct(
        string $name,
        public int $quantity,
        public int $unitPrice,
    ) {
        $trimmedName = trim($name);

        if ($trimmedName === '') {
            throw InvalidProductLineException::emptyName();
        }

        if (mb_strlen($trimmedName) > 255) {
            throw InvalidProductLineException::nameTooLong();
        }

        if ($this->quantity <= 0) {
            throw InvalidProductLineException::nonPositiveQuantity($this->quantity);
        }

        if ($this->unitPrice <= 0) {
            throw InvalidProductLineException::nonPositiveUnitPrice($this->unitPrice);
        }

        if ($this->quantity > intdiv(PHP_INT_MAX, $this->unitPrice)) {
            throw InvalidProductLineException::totalOverflow();
        }

        $this->name = $trimmedName;
    }

    public function totalUnitPrice(): int
    {
        return $this->quantity * $this->unitPrice;
    }
}
