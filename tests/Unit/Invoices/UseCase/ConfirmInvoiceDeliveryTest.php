<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\UseCase;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvoiceDeliveryCannotBeConfirmedException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotStoredException;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\UseCase\ConfirmInvoiceDelivery;
use Modules\Invoices\UseCase\ConfirmInvoiceDeliveryRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ConfirmInvoiceDeliveryTest extends TestCase
{
    private InvoiceRepositoryInterface&MockObject $invoiceRepository;

    private ConfirmInvoiceDelivery $confirmInvoiceDelivery;

    protected function setUp(): void
    {
        $this->invoiceRepository = $this->createMock(InvoiceRepositoryInterface::class);

        $this->confirmInvoiceDelivery = new ConfirmInvoiceDelivery(
            $this->invoiceRepository,
        );
    }

    public function test_it_saves_an_invoice_being_sent_as_sent_to_client(): void
    {
        $invoice = $this->storedInvoiceBeingSent();

        $this->invoiceRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(static fn (Invoice $saved): bool => $saved === $invoice
                && $saved->status === StatusEnum::SentToClient));

        $this->assertSame(
            $invoice,
            $this->confirmInvoiceDelivery->execute(new ConfirmInvoiceDeliveryRequest($invoice->id)),
        );
    }

    public function test_a_missing_invoice_is_reported(): void
    {
        $invoiceId = Uuid::uuid7();
        $failure = new InvoiceNotFoundException($invoiceId);
        $this->invoiceRepository->method('findById')->willThrowException($failure);

        $this->invoiceRepository->expects($this->never())->method('save');

        $this->expectExceptionObject($failure);

        $this->confirmInvoiceDelivery->execute(new ConfirmInvoiceDeliveryRequest($invoiceId));
    }

    public function test_the_delivery_of_an_invoice_that_is_not_being_sent_is_not_confirmed(): void
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com', new ProductLine('Keyboard', 2, 150));
        $this->invoiceRepository->method('findById')->willReturn($invoice);

        $this->invoiceRepository->expects($this->never())->method('save');

        $this->expectException(InvoiceDeliveryCannotBeConfirmedException::class);

        try {
            $this->confirmInvoiceDelivery->execute(new ConfirmInvoiceDeliveryRequest($invoice->id));
        } finally {
            $this->assertSame(StatusEnum::Draft, $invoice->status);
        }
    }

    public function test_a_storage_failure_is_reported(): void
    {
        $invoice = $this->storedInvoiceBeingSent();
        $failure = new InvoiceNotStoredException($invoice->id);
        $this->invoiceRepository->method('save')->willThrowException($failure);

        $this->expectExceptionObject($failure);

        $this->confirmInvoiceDelivery->execute(new ConfirmInvoiceDeliveryRequest($invoice->id));
    }

    /** Creates an invoice in "sending" status and makes the repository return it by its ID. */
    private function storedInvoiceBeingSent(): Invoice
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com', new ProductLine('Keyboard', 2, 150));
        $invoice->sendToCustomer();

        $this->invoiceRepository->method('findById')
            ->with($this->callback(static fn ($id): bool => $invoice->id->equals($id)))
            ->willReturn($invoice);

        return $invoice;
    }
}
