<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Notifications;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\UseCase\CustomerNotifierInterface;
use Modules\Invoices\UseCase\Exceptions\CustomerNotNotifiedException;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;
use Throwable;

/**
 * Emails the customer through the Notifications module. The invoice ID is
 * the notification's reference, which is how the delivery webhook finds its
 * way back to the invoice (see ConfirmInvoiceDeliveryOnWebhookDelivered).
 */
final readonly class NotificationsCustomerNotifier implements CustomerNotifierInterface
{
    public function __construct(
        private NotificationFacadeInterface $notificationFacade,
    ) {}

    public function notifyInvoiceIssued(Invoice $invoice): void
    {
        try {
            $this->notificationFacade->notify(new NotifyData(
                resourceId: $invoice->id,
                toEmail: $invoice->customerEmail,
                subject: sprintf('Invoice %s', $invoice->id->toString()),
                message: sprintf(
                    'Dear %s, your invoice for a total of %d is ready.',
                    $invoice->customerName,
                    $invoice->totalPrice(),
                ),
            ));
        } catch (Throwable $exception) {
            throw new CustomerNotNotifiedException($invoice->id, $exception);
        }
    }
}
