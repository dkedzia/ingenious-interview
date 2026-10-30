<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Domain;

use Modules\Invoices\Domain\Exceptions\InvalidProductLineException;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProductLineTest extends TestCase
{
    #[Test]
    public function shouldCreateWithValidData(): void
    {
        $line = new ProductLine('Widget', 3, 500);

        $this->assertSame('Widget', $line->name);
        $this->assertSame(3, $line->quantity);
        $this->assertSame(500, $line->unitPrice);
    }

    #[Test]
    public function shouldCalculateTotalUnitPrice(): void
    {
        $line = new ProductLine('Widget', 3, 500);

        $this->assertSame(1500, $line->totalUnitPrice());
    }

    #[Test]
    public function shouldRejectZeroQuantity(): void
    {
        $this->expectException(InvalidProductLineException::class);

        new ProductLine('Widget', 0, 500);
    }

    #[Test]
    public function shouldRejectNegativeQuantity(): void
    {
        $this->expectException(InvalidProductLineException::class);

        new ProductLine('Widget', -1, 500);
    }

    #[Test]
    public function shouldRejectZeroUnitPrice(): void
    {
        $this->expectException(InvalidProductLineException::class);

        new ProductLine('Widget', 1, 0);
    }

    #[Test]
    public function shouldRejectNegativeUnitPrice(): void
    {
        $this->expectException(InvalidProductLineException::class);

        new ProductLine('Widget', 1, -100);
    }

    #[Test]
    public function shouldRejectEmptyName(): void
    {
        $this->expectException(InvalidProductLineException::class);

        new ProductLine('', 1, 100);
    }

    #[Test]
    public function shouldRejectBlankName(): void
    {
        $this->expectException(InvalidProductLineException::class);

        new ProductLine('   ', 1, 100);
    }

    #[Test]
    public function shouldRejectNameLongerThan255Characters(): void
    {
        $this->expectException(InvalidProductLineException::class);

        new ProductLine(str_repeat('a', 256), 1, 100);
    }

    #[Test]
    public function shouldRejectOverflowingTotal(): void
    {
        $this->expectException(InvalidProductLineException::class);

        new ProductLine('Widget', 2, PHP_INT_MAX);
    }
}
