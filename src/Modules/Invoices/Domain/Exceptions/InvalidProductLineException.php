<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;

final class InvalidProductLineException extends DomainException
{
    public static function emptyName(): self
    {
        return new self('Product name must not be empty.');
    }

    public static function nameTooLong(): self
    {
        return new self('Product name must not exceed 255 characters.');
    }

    public static function nonPositiveQuantity(int $quantity): self
    {
        return new self("Quantity must be a positive integer, got {$quantity}.");
    }

    public static function nonPositiveUnitPrice(int $unitPrice): self
    {
        return new self("Unit price must be a positive integer, got {$unitPrice}.");
    }

    public static function totalOverflow(): self
    {
        return new self('Product line total exceeds the allowed integer range.');
    }
}
