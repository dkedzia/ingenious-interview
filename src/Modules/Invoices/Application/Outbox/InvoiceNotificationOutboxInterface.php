<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Outbox;

use DateTimeInterface;
use Ramsey\Uuid\UuidInterface;

interface InvoiceNotificationOutboxInterface
{
    public function enqueuePending(UuidInterface $invoiceId): void;

    public function isNotified(UuidInterface $invoiceId): bool;

    public function claim(UuidInterface $invoiceId, DateTimeInterface $staleBefore): ?string;

    public function ownsClaim(UuidInterface $invoiceId, string $token): bool;

    public function markNotified(UuidInterface $invoiceId, string $token): bool;

    public function markFailed(UuidInterface $invoiceId, string $token): bool;

    public function revertSendingIfClaimHeld(UuidInterface $invoiceId, string $token): bool;

    /** @return UuidInterface[] */
    public function idsDueForDelivery(DateTimeInterface $staleBefore): array;
}
