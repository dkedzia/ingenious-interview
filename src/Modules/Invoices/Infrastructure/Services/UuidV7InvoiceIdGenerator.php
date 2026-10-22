<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Services;

use Modules\Invoices\Domain\Services\InvoiceIdGenerator;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class UuidV7InvoiceIdGenerator implements InvoiceIdGenerator
{
    public function generate(): UuidInterface
    {
        return Uuid::uuid7();
    }
}
