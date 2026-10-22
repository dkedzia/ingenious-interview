<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Repositories;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotStoredException;
use Ramsey\Uuid\UuidInterface;

interface InvoiceRepository
{
    /**
     * @throws InvoiceNotFoundException
     */
    public function findById(UuidInterface $invoiceId): Invoice;

    /**
     * @throws InvoiceNotStoredException
     */
    public function save(Invoice $invoice): void;
}
