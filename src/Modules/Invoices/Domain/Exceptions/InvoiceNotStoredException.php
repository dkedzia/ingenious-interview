<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use Ramsey\Uuid\UuidInterface;
use Throwable;

final class InvoiceNotStoredException extends InvoicesDomainException
{
    public function __construct(
        public readonly UuidInterface $invoiceId,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Invoice %s could not be stored.', $invoiceId->toString()),
            previous: $previous,
        );
    }
}
