<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Listeners;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvoiceDeliveryCannotBeConfirmedException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\UseCase\ConfirmInvoiceDelivery\ConfirmInvoiceDelivery;
use Modules\Invoices\UseCase\ConfirmInvoiceDelivery\ConfirmInvoiceDeliveryRequest;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;
use Psr\Log\LoggerInterface;

/**
 * Translates the Notifications module's webhook into the Invoices use case.
 *
 * Whatever this listener lets through fails the webhook, which tells the
 * provider to retry, so only failures that a retry can fix are let through:
 *
 * - a reference that is not an invoice, or an invoice already sent to the
 *   client (a repeated webhook), stays that way; it is logged and ignored
 * - a draft invoice is most likely a race with SendInvoice, which saves the
 *   "sending" status only after the notification has gone out, so the webhook
 *   can arrive in between; a retry finds the invoice being sent
 * - a storage failure is temporary
 */
final readonly class ConfirmInvoiceDeliveryOnWebhookDelivered
{
    public function __construct(
        private ConfirmInvoiceDelivery $confirmInvoiceDelivery,
        private LoggerInterface $logger,
    ) {}

    /** @throws InvoiceDeliveryCannotBeConfirmedException when the invoice is still a draft */
    public function handle(WebhookDeliveredEvent $event): void
    {
        try {
            $this->confirmInvoiceDelivery->execute(new ConfirmInvoiceDeliveryRequest($event->resourceId));
        } catch (InvoiceNotFoundException) {
            $this->logger->info('Ignored a delivery confirmation for a resource that is not an invoice.', [
                'resource_id' => $event->resourceId->toString(),
            ]);
        } catch (InvoiceDeliveryCannotBeConfirmedException $exception) {
            if ($exception->status === StatusEnum::Draft) {
                throw $exception;
            }

            $this->logger->warning('Ignored a delivery confirmation for an invoice that is not being sent.', [
                'resource_id' => $event->resourceId->toString(),
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
