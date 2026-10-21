<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use Ramsey\Uuid\UuidInterface;

final class InvoiceNotFoundException extends InvoicesDomainException
{
    public function __construct(
        public readonly UuidInterface $invoiceId,
    ) {
        parent::__construct(sprintf('Invoice %s does not exist.', $invoiceId->toString()));
    }
}
