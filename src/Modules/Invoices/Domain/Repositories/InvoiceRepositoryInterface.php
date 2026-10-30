<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Repositories;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Invoice;
use Ramsey\Uuid\UuidInterface;

interface InvoiceRepositoryInterface
{
    public function get(UuidInterface $id): Invoice;

    public function find(UuidInterface $id): ?Invoice;

    public function save(Invoice $invoice, ?StatusEnum $expectedStatus = null): void;
}
