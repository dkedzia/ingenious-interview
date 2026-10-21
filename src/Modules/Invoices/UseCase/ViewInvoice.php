<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;

final readonly class ViewInvoice
{
    public function __construct(
        private InvoiceRepositoryInterface $invoiceRepository,
    ) {}

    /** @throws InvoiceNotFoundException */
    public function execute(ViewInvoiceRequest $request): Invoice
    {
        return $this->invoiceRepository->findById($request->invoiceId);
    }
}
