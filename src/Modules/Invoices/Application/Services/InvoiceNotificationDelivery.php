<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Services;

use Modules\Invoices\Application\Exceptions\SendInvoiceCompensationException;
use Modules\Invoices\Application\Outbox\InvoiceNotificationOutboxInterface;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;
use Ramsey\Uuid\UuidInterface;
use Throwable;

final readonly class InvoiceNotificationDelivery implements InvoiceNotificationDeliveryInterface
{
    public const STALE_AFTER_SECONDS = 120;

    public function __construct(
        private InvoiceRepositoryInterface $invoices,
        private InvoiceNotificationOutboxInterface $outbox,
        private NotificationFacadeInterface $notifications,
    ) {}

    public function deliver(UuidInterface $invoiceId): void
    {
        $token = $this->outbox->claim($invoiceId, now()->subSeconds(self::STALE_AFTER_SECONDS));

        if ($token === null) {
            return;
        }

        $invoice = $this->invoices->get($invoiceId);

        if ($invoice->status() === StatusEnum::SentToClient) {
            $this->outbox->markNotified($invoiceId, $token);

            return;
        }

        if ($invoice->status() !== StatusEnum::Sending) {
            $this->outbox->markFailed($invoiceId, $token);

            return;
        }

        try {
            $this->notifications->notify(new NotifyData(
                resourceId: $invoice->id(),
                toEmail: $invoice->customerEmail(),
                subject: 'Invoice',
                message: 'Your invoice is ready.',
            ));
        } catch (Throwable $notificationFailure) {
            $this->compensateFailedNotify($invoiceId, $token, $notificationFailure);

            throw $notificationFailure;
        }

        $this->outbox->markNotified($invoiceId, $token);
    }

    /** @return UuidInterface[] */
    public function dueInvoiceIds(): array
    {
        return $this->outbox->idsDueForDelivery(now()->subSeconds(self::STALE_AFTER_SECONDS));
    }

    private function compensateFailedNotify(
        UuidInterface $invoiceId,
        string $token,
        Throwable $notificationFailure,
    ): void {
        try {
            if ($this->outbox->revertSendingIfClaimHeld($invoiceId, $token)) {
                return;
            }

            if (! $this->outbox->ownsClaim($invoiceId, $token)) {
                return;
            }

            $current = $this->invoices->get($invoiceId);

            if ($current->status() === StatusEnum::SentToClient) {
                $this->outbox->markNotified($invoiceId, $token);
            }
        } catch (Throwable $compensationFailure) {
            throw new SendInvoiceCompensationException($notificationFailure, $compensationFailure);
        }
    }
}
