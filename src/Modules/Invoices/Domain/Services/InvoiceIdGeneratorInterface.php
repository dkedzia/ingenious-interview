<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Services;

use Ramsey\Uuid\UuidInterface;

interface InvoiceIdGeneratorInterface
{
    public function generate(): UuidInterface;
}
