<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Infrastructure\Services;

use Modules\Invoices\Infrastructure\Services\UuidV7InvoiceIdGenerator;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Rfc4122\FieldsInterface;

final class UuidV7InvoiceIdGeneratorTest extends TestCase
{
    public function test_it_generates_distinct_time_ordered_ids(): void
    {
        $generator = new UuidV7InvoiceIdGenerator;

        $first = $generator->generate();
        $second = $generator->generate();

        $fields = $first->getFields();
        $this->assertInstanceOf(FieldsInterface::class, $fields);
        $this->assertSame(7, $fields->getVersion());
        $this->assertFalse($first->equals($second));
        $this->assertLessThan(0, $first->compareTo($second));
    }
}
