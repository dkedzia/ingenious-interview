<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;

final class EmptyProductLinesException extends DomainException
{
    public static function cannotSend(): self
    {
        return new self('Invoice must contain at least one product line to be sent.');
    }
}
