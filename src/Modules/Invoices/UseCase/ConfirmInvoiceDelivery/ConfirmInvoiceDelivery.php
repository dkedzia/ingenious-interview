<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase\ConfirmInvoiceDelivery;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Exceptions\InvoiceDeliveryCannotBeConfirmedException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotStoredException;
use Modules\Invoices\Domain\Repositories\InvoiceRepository;

final readonly class ConfirmInvoiceDelivery
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
    ) {}

    /**
     * @throws InvoiceNotFoundException
     * @throws InvoiceDeliveryCannotBeConfirmedException
     * @throws InvoiceNotStoredException
     */
    public function execute(ConfirmInvoiceDeliveryRequest $request): Invoice
    {
        $invoice = $this->invoiceRepository->findById($request->invoiceId);
        $invoice->confirmDelivery();
        $this->invoiceRepository->save($invoice);

        return $invoice;
    }
}
