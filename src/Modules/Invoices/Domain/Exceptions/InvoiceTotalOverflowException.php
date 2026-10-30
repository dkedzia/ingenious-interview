<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;

final class InvoiceTotalOverflowException extends DomainException
{
    public static function tooLarge(): self
    {
        return new self('Invoice total price exceeds the allowed integer range.');
    }
}
