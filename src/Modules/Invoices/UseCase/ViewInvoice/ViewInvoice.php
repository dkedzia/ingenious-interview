<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase\ViewInvoice;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Domain\Repositories\InvoiceRepository;

final readonly class ViewInvoice
{
    public function __construct(
        private InvoiceRepository $invoiceRepository,
    ) {}

    /** @throws InvoiceNotFoundException */
    public function execute(ViewInvoiceRequest $request): Invoice
    {
        return $this->invoiceRepository->findById($request->invoiceId);
    }
}
