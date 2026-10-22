<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Exceptions\InvoiceDeliveryCannotBeConfirmedException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotStoredException;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;

final readonly class ConfirmInvoiceDelivery
{
    public function __construct(
        private InvoiceRepositoryInterface $invoiceRepository,
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
