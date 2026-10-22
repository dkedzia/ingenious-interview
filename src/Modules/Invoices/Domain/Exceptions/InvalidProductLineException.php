<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

final class InvalidProductLineException extends InvoicesDomainException
{
    public static function blankName(): self
    {
        return new self('The product name must not be blank.');
    }

    public static function nonPositiveQuantity(int $quantity): self
    {
        return new self(sprintf('The quantity must be a positive integer, %d given.', $quantity));
    }

    public static function nonPositiveUnitPrice(int $unitPrice): self
    {
        return new self(sprintf('The unit price must be a positive integer, %d given.', $unitPrice));
    }
}
