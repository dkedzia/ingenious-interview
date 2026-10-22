<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase\CreateInvoice;

use Modules\Invoices\UseCase\Exceptions\InvoicesUseCaseException;

final class InvalidCreateInvoiceRequestException extends InvoicesUseCaseException
{
    /** @param non-empty-list<Violation> $violations */
    public function __construct(
        public readonly array $violations,
    ) {
        parent::__construct('The invoice could not be created because the request is invalid.');
    }
}
