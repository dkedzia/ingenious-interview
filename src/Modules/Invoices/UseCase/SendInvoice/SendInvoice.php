<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase\SendInvoice;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSentException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotStoredException;
use Modules\Invoices\Domain\Repositories\InvoiceRepository;

final readonly class SendInvoice
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private CustomerNotifier $customerNotifier,
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
            $this->customerNotifier->notifyInvoiceIssued($invoice);
        } catch (CustomerNotNotifiedException $exception) {
            throw new InvoiceNotSentException($invoice->id, $exception);
        }

        $this->invoiceRepository->save($invoice);

        return $invoice;
    }
}
