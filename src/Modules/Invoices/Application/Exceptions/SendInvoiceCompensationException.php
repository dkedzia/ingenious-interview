<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Exceptions;

use RuntimeException;
use Throwable;

final class SendInvoiceCompensationException extends RuntimeException
{
    public function __construct(
        Throwable $notificationFailure,
        public readonly Throwable $compensationFailure,
    ) {
        parent::__construct($notificationFailure->getMessage(), 0, $notificationFailure);
    }
}
