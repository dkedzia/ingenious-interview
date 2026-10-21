<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSentException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotStoredException;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\UseCase\Exceptions\InvoiceNotSentException;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;
use Throwable;

final readonly class SendInvoice
{
    public function __construct(
        private InvoiceRepositoryInterface $invoiceRepository,
        private NotificationFacadeInterface $notificationFacade,
    ) {}

    /**
     * @throws InvoiceNotFoundException
     * @throws InvoiceCannotBeSentException
     * @throws InvoiceNotSentException
     * @throws InvoiceNotStoredException
     */
    public function execute(SendInvoiceRequest $request): Invoice
    {
        $invoice = $this->invoiceRepository->findById($request->invoiceId);

        $invoice->sendToCustomer();

        try {
            $this->notificationFacade->notify(self::notificationFor($invoice));
        } catch (Throwable $exception) {
            throw new InvoiceNotSentException($invoice->id, $exception);
        }

        $this->invoiceRepository->save($invoice);

        return $invoice;
    }

    private static function notificationFor(Invoice $invoice): NotifyData
    {
        return new NotifyData(
            resourceId: $invoice->id,
            toEmail: $invoice->customerEmail,
            subject: "Invoice {$invoice->id->toString()}",
            message: "Dear {$invoice->customerName}, your invoice for a total of {$invoice->totalPrice()} is ready.",
        );
    }
}
