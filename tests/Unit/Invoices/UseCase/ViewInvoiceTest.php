<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\UseCase;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\UseCase\ViewInvoice;
use Modules\Invoices\UseCase\ViewInvoiceRequest;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ViewInvoiceTest extends TestCase
{
    private InvoiceRepositoryInterface&Stub $invoiceRepository;

    private ViewInvoice $viewInvoice;

    protected function setUp(): void
    {
        $this->invoiceRepository = $this->createStub(InvoiceRepositoryInterface::class);

        $this->viewInvoice = new ViewInvoice(
            $this->invoiceRepository,
        );
    }

    public function test_it_returns_the_invoice_with_the_requested_id(): void
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com');
        $this->invoiceRepository->method('findById')
            ->willReturnCallback(static fn ($id): Invoice => $invoice->id->equals($id)
                ? $invoice
                : throw new InvoiceNotFoundException($id));

        $this->assertSame($invoice, $this->viewInvoice->execute(new ViewInvoiceRequest($invoice->id)));
    }

    public function test_a_missing_invoice_is_reported(): void
    {
        $invoiceId = Uuid::uuid7();
        $failure = new InvoiceNotFoundException($invoiceId);
        $this->invoiceRepository->method('findById')->willThrowException($failure);

        $this->expectExceptionObject($failure);

        $this->viewInvoice->execute(new ViewInvoiceRequest($invoiceId));
    }
}
