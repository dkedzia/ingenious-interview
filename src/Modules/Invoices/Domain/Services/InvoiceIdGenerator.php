<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Services;

use Ramsey\Uuid\UuidInterface;

interface InvoiceIdGenerator
{
    public function generate(): UuidInterface;
}
