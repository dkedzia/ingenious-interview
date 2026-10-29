<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Application;

use Modules\Invoices\Application\Commands\CreateInvoiceCommand;
use Modules\Invoices\Application\DTOs\ProductLineData;
use Modules\Invoices\Application\Handlers\CreateInvoiceHandler;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Models\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreateInvoiceHandlerTest extends TestCase
{
    #[Test]
    public function shouldCreateDraftInvoiceWithProductLines(): void
    {
        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Invoice::class));

        $handler = new CreateInvoiceHandler($repository);
        $command = new CreateInvoiceCommand(
            customerName: 'John Doe',
            customerEmail: 'john@example.com',
            productLines: [
                new ProductLineData('Widget', 2, 300),
                new ProductLineData('Gadget', 1, 700),
            ],
        );

        $invoice = $handler->handle($command);

        $this->assertSame(StatusEnum::Draft, $invoice->status());
        $this->assertSame('John Doe', $invoice->customerName());
        $this->assertSame('john@example.com', $invoice->customerEmail());
        $this->assertCount(2, $invoice->productLines());
        $this->assertSame(1300, $invoice->totalPrice());
    }

    #[Test]
    public function shouldCreateDraftInvoiceWithoutProductLines(): void
    {
        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->expects($this->once())->method('save');

        $handler = new CreateInvoiceHandler($repository);
        $command = new CreateInvoiceCommand(
            customerName: 'Jane',
            customerEmail: 'jane@example.com',
        );

        $invoice = $handler->handle($command);

        $this->assertSame(StatusEnum::Draft, $invoice->status());
        $this->assertEmpty($invoice->productLines());
    }
}
