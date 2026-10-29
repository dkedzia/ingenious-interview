<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use InvalidArgumentException;

final class InvalidProductLineException extends InvalidArgumentException
{
    public static function nonPositiveQuantity(int $quantity): self
    {
        return new self("Quantity must be a positive integer, got {$quantity}.");
    }

    public static function nonPositiveUnitPrice(int $unitPrice): self
    {
        return new self("Unit price must be a positive integer, got {$unitPrice}.");
    }
}
