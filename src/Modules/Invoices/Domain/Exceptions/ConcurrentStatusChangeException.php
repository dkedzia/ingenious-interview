<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;
use Ramsey\Uuid\UuidInterface;

final class ConcurrentStatusChangeException extends DomainException
{
    public static function forInvoice(UuidInterface $id): self
    {
        return new self("Invoice {$id->toString()} status changed concurrently.");
    }
}
