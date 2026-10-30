<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Domain;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\EmptyProductLinesException;
use Modules\Invoices\Domain\Exceptions\InvalidCustomerException;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Exceptions\InvoiceTotalOverflowException;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class InvoiceTest extends TestCase
{
    #[Test]
    public function shouldInitializeWithDraftStatusOnCreate(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::create($id, 'John Doe', 'john@example.com');

        $this->assertTrue($id->equals($invoice->id()));
        $this->assertSame('John Doe', $invoice->customerName());
        $this->assertSame('john@example.com', $invoice->customerEmail());
        $this->assertSame(StatusEnum::Draft, $invoice->status());
        $this->assertEmpty($invoice->productLines());
    }

    #[Test]
    public function shouldCreateWithProductLines(): void
    {
        $lines = [
            new ProductLine('Widget', 2, 300),
            new ProductLine('Gadget', 1, 700),
        ];

        $invoice = Invoice::create(Uuid::uuid4(), 'Jane Doe', 'jane@example.com', $lines);

        $this->assertCount(2, $invoice->productLines());
    }

    #[Test]
    public function shouldCalculateTotalPrice(): void
    {
        $lines = [
            new ProductLine('Widget', 2, 300),
            new ProductLine('Gadget', 3, 500),
        ];

        $invoice = Invoice::create(Uuid::uuid4(), 'Jane', 'jane@example.com', $lines);

        $this->assertSame(2100, $invoice->totalPrice());
    }

    #[Test]
    public function shouldRejectOverflowingInvoiceTotal(): void
    {
        $this->expectException(InvoiceTotalOverflowException::class);

        Invoice::create(Uuid::uuid4(), 'Jane', 'jane@example.com', [
            new ProductLine('Widget', 1, PHP_INT_MAX),
            new ProductLine('Gadget', 1, 1),
        ]);
    }

    #[Test]
    public function shouldReturnZeroTotalPriceWithNoLines(): void
    {
        $invoice = Invoice::create(Uuid::uuid4(), 'Jane', 'jane@example.com');

        $this->assertSame(0, $invoice->totalPrice());
    }

    #[Test]
    public function shouldTransitionToSendingFromDraftWithLines(): void
    {
        $lines = [new ProductLine('Widget', 1, 100)];
        $invoice = Invoice::create(Uuid::uuid4(), 'Jane', 'jane@example.com', $lines);

        $invoice->markAsSending();

        $this->assertSame(StatusEnum::Sending, $invoice->status());
    }

    #[Test]
    public function shouldRejectSendingFromDraftWithoutLines(): void
    {
        $invoice = Invoice::create(Uuid::uuid4(), 'Jane', 'jane@example.com');

        $this->expectException(EmptyProductLinesException::class);

        $invoice->markAsSending();
    }

    #[Test]
    public function shouldRejectSendingFromSendingStatus(): void
    {
        $invoice = $this->createSendingInvoice();

        $this->expectException(InvalidStatusTransitionException::class);

        $invoice->markAsSending();
    }

    #[Test]
    public function shouldRejectSendingFromSentToClientStatus(): void
    {
        $invoice = Invoice::reconstitute(
            Uuid::uuid4(),
            'Jane',
            'jane@example.com',
            StatusEnum::SentToClient,
            [new ProductLine('Widget', 1, 100)],
        );

        $this->expectException(InvalidStatusTransitionException::class);

        $invoice->markAsSending();
    }

    #[Test]
    public function shouldTransitionToSentToClientFromSending(): void
    {
        $invoice = $this->createSendingInvoice();

        $invoice->markAsSentToClient();

        $this->assertSame(StatusEnum::SentToClient, $invoice->status());
    }

    #[Test]
    public function shouldRejectSentToClientFromDraftStatus(): void
    {
        $lines = [new ProductLine('Widget', 1, 100)];
        $invoice = Invoice::create(Uuid::uuid4(), 'Jane', 'jane@example.com', $lines);

        $this->expectException(InvalidStatusTransitionException::class);

        $invoice->markAsSentToClient();
    }

    #[Test]
    public function shouldRevertSendingToDraft(): void
    {
        $invoice = $this->createSendingInvoice();

        $invoice->revertSending();

        $this->assertSame(StatusEnum::Draft, $invoice->status());
    }

    #[Test]
    public function shouldRejectRevertSendingFromDraft(): void
    {
        $invoice = Invoice::create(Uuid::uuid4(), 'Jane', 'jane@example.com', [
            new ProductLine('Widget', 1, 100),
        ]);

        $this->expectException(InvalidStatusTransitionException::class);

        $invoice->revertSending();
    }

    #[Test]
    public function shouldRejectEmptyCustomerName(): void
    {
        $this->expectException(InvalidCustomerException::class);

        Invoice::create(Uuid::uuid4(), '   ', 'jane@example.com');
    }

    #[Test]
    public function shouldRejectInvalidCustomerEmail(): void
    {
        $this->expectException(InvalidCustomerException::class);

        Invoice::create(Uuid::uuid4(), 'Jane', 'not-an-email');
    }

    #[Test]
    public function shouldRejectSentToClientFromSentToClientStatus(): void
    {
        $invoice = Invoice::reconstitute(
            Uuid::uuid4(),
            'Jane',
            'jane@example.com',
            StatusEnum::SentToClient,
            [new ProductLine('Widget', 1, 100)],
        );

        $this->expectException(InvalidStatusTransitionException::class);

        $invoice->markAsSentToClient();
    }

    private function createSendingInvoice(): Invoice
    {
        return Invoice::reconstitute(
            Uuid::uuid4(),
            'Jane',
            'jane@example.com',
            StatusEnum::Sending,
            [new ProductLine('Widget', 1, 100)],
        );
    }
}
