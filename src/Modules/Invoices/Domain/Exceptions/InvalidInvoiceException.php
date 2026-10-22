<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

final class InvalidInvoiceException extends InvoicesDomainException
{
    public static function blankCustomerName(): self
    {
        return new self('The customer name must not be blank.');
    }

    public static function invalidCustomerEmail(string $email): self
    {
        return new self(sprintf('"%s" is not a valid customer email.', $email));
    }
}
