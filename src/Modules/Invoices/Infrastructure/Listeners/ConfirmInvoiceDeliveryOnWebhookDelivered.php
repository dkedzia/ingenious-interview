<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Listeners;

use Modules\Invoices\Domain\Exceptions\InvoiceDeliveryCannotBeConfirmedException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\UseCase\ConfirmInvoiceDelivery;
use Modules\Invoices\UseCase\ConfirmInvoiceDeliveryRequest;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;
use Psr\Log\LoggerInterface;

/**
 * Translates the Notifications module's webhook into the Invoices use case.
 *
 * The provider calls the webhook for every notification and may call it more
 * than once, so a reference that is not an invoice, or an invoice that is not
 * being sent, is logged and ignored rather than failing the webhook. A storage
 * failure still propagates, so the webhook fails and the provider can retry.
 */
final readonly class ConfirmInvoiceDeliveryOnWebhookDelivered
{
    public function __construct(
        private ConfirmInvoiceDelivery $confirmInvoiceDelivery,
        private LoggerInterface $logger,
    ) {}

    public function handle(WebhookDeliveredEvent $event): void
    {
        try {
            $this->confirmInvoiceDelivery->execute(new ConfirmInvoiceDeliveryRequest($event->resourceId));
        } catch (InvoiceNotFoundException) {
            $this->logger->info('Ignored a delivery confirmation for a resource that is not an invoice.', [
                'resource_id' => $event->resourceId->toString(),
            ]);
        } catch (InvoiceDeliveryCannotBeConfirmedException $exception) {
            $this->logger->warning('Ignored a delivery confirmation for an invoice that is not being sent.', [
                'resource_id' => $event->resourceId->toString(),
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
