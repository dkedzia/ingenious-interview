<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Ramsey\Uuid\UuidInterface;

final class InvoiceDeliveryCannotBeConfirmedException extends InvoicesDomainException
{
    public static function notSending(UuidInterface $invoiceId, StatusEnum $status): self
    {
        return new self(
            sprintf(
                'The delivery of invoice %s cannot be confirmed because its status is "%s"; only invoices being sent can be delivered.',
                $invoiceId->toString(),
                $status->value,
            )
        );
    }
}
