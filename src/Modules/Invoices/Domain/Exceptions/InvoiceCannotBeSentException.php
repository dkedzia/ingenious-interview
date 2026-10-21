<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Ramsey\Uuid\UuidInterface;

final class InvoiceCannotBeSentException extends InvoicesDomainException
{
    public static function notDraft(UuidInterface $invoiceId, StatusEnum $status): self
    {
        return new self(
            sprintf(
                'Invoice %s cannot be sent because its status is "%s"; only draft invoices can be sent.',
                $invoiceId->toString(),
                $status->value,
            )
        );
    }

    public static function noProductLines(UuidInterface $invoiceId): self
    {
        return new self(
            sprintf(
                'Invoice %s cannot be sent because it has no product lines.',
                $invoiceId->toString(),
            )
        );
    }
}
