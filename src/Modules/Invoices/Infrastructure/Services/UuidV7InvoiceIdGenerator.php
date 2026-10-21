<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Services;

use Modules\Invoices\Domain\Services\InvoiceIdGeneratorInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class UuidV7InvoiceIdGenerator implements InvoiceIdGeneratorInterface
{
    public function generate(): UuidInterface
    {
        return Uuid::uuid7();
    }
}
