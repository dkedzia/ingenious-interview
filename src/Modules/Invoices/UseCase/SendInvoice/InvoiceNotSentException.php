<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase\SendInvoice;

use Modules\Invoices\UseCase\Exceptions\InvoicesUseCaseException;
use Ramsey\Uuid\UuidInterface;
use Throwable;

final class InvoiceNotSentException extends InvoicesUseCaseException
{
    public function __construct(
        public readonly UuidInterface $invoiceId,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Invoice %s could not be sent to the customer.', $invoiceId->toString()),
            previous: $previous,
        );
    }
}
