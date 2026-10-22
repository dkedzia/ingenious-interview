<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase\SendInvoice;

use Modules\Invoices\UseCase\Exceptions\InvoicesUseCaseException;
use Ramsey\Uuid\UuidInterface;
use Throwable;

final class CustomerNotNotifiedException extends InvoicesUseCaseException
{
    public function __construct(
        public readonly UuidInterface $invoiceId,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('The customer of invoice %s could not be notified.', $invoiceId->toString()),
            previous: $previous,
        );
    }
}
