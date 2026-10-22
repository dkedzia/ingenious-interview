<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\UseCase\SendInvoice;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSentException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotStoredException;
use Modules\Invoices\Domain\Repositories\InvoiceRepository;
use Modules\Invoices\UseCase\SendInvoice\CustomerNotifier;
use Modules\Invoices\UseCase\SendInvoice\CustomerNotNotifiedException;
use Modules\Invoices\UseCase\SendInvoice\InvoiceNotSentException;
use Modules\Invoices\UseCase\SendInvoice\SendInvoice;
use Modules\Invoices\UseCase\SendInvoice\SendInvoiceRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class SendInvoiceTest extends TestCase
{
    private InvoiceRepository&MockObject $invoiceRepository;

    private CustomerNotifier&MockObject $customerNotifier;

    private SendInvoice $sendInvoice;

    protected function setUp(): void
    {
        $this->invoiceRepository = $this->createMock(InvoiceRepository::class);
        $this->customerNotifier = $this->createMock(CustomerNotifier::class);

        $this->sendInvoice = new SendInvoice(
            $this->invoiceRepository,
            $this->customerNotifier,
        );
    }

    public function test_it_notifies_the_customer_and_saves_the_invoice_as_sending(): void
    {
        $invoice = $this->storedInvoice(new ProductLine('Keyboard', 2, 150));

        $this->customerNotifier->expects($this->once())
            ->method('notifyInvoiceIssued')
            ->with($this->identicalTo($invoice));

        $this->invoiceRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(static fn (Invoice $saved): bool => $saved === $invoice
                && $saved->status === StatusEnum::Sending));

        $this->assertSame($invoice, $this->sendInvoice->execute(new SendInvoiceRequest($invoice->id)));
    }

    public function test_the_customer_is_notified_before_the_new_status_is_saved(): void
    {
        $invoice = $this->storedInvoice(new ProductLine('Keyboard', 2, 150));
        $calls = [];

        $this->customerNotifier->method('notifyInvoiceIssued')->willReturnCallback(static function () use (&$calls): void {
            $calls[] = 'notify';
        });
        $this->invoiceRepository->method('save')->willReturnCallback(static function () use (&$calls): void {
            $calls[] = 'save';
        });

        $this->sendInvoice->execute(new SendInvoiceRequest($invoice->id));

        $this->assertSame(['notify', 'save'], $calls);
    }

    public function test_a_missing_invoice_is_reported(): void
    {
        $invoiceId = Uuid::uuid7();
        $failure = new InvoiceNotFoundException($invoiceId);
        $this->invoiceRepository->method('findById')->willThrowException($failure);

        $this->customerNotifier->expects($this->never())->method('notifyInvoiceIssued');
        $this->invoiceRepository->expects($this->never())->method('save');

        $this->expectExceptionObject($failure);

        $this->sendInvoice->execute(new SendInvoiceRequest($invoiceId));
    }

    public function test_an_invoice_without_product_lines_is_not_sent(): void
    {
        $invoice = $this->storedInvoice();

        $this->customerNotifier->expects($this->never())->method('notifyInvoiceIssued');
        $this->invoiceRepository->expects($this->never())->method('save');

        $this->expectException(InvoiceCannotBeSentException::class);

        try {
            $this->sendInvoice->execute(new SendInvoiceRequest($invoice->id));
        } finally {
            $this->assertSame(StatusEnum::Draft, $invoice->status);
        }
    }

    public function test_an_invoice_that_is_not_a_draft_is_not_sent(): void
    {
        $invoice = $this->storedInvoice(new ProductLine('Keyboard', 2, 150));
        $invoice->sendToCustomer();

        $this->customerNotifier->expects($this->never())->method('notifyInvoiceIssued');
        $this->invoiceRepository->expects($this->never())->method('save');

        $this->expectException(InvoiceCannotBeSentException::class);

        $this->sendInvoice->execute(new SendInvoiceRequest($invoice->id));
    }

    public function test_a_failed_notification_is_reported_and_the_invoice_is_not_saved(): void
    {
        $invoice = $this->storedInvoice(new ProductLine('Keyboard', 2, 150));
        $cause = new CustomerNotNotifiedException($invoice->id);
        $this->customerNotifier->method('notifyInvoiceIssued')->willThrowException($cause);

        $this->invoiceRepository->expects($this->never())->method('save');

        try {
            $this->sendInvoice->execute(new SendInvoiceRequest($invoice->id));

            $this->fail('Expected the invoice not to be sent.');
        } catch (InvoiceNotSentException $exception) {
            $this->assertTrue($exception->invoiceId->equals($invoice->id));
            $this->assertSame($cause, $exception->getPrevious());
        }
    }

    public function test_a_storage_failure_is_reported(): void
    {
        $invoice = $this->storedInvoice(new ProductLine('Keyboard', 2, 150));
        $failure = new InvoiceNotStoredException($invoice->id);
        $this->invoiceRepository->method('save')->willThrowException($failure);

        $this->expectExceptionObject($failure);

        $this->sendInvoice->execute(new SendInvoiceRequest($invoice->id));
    }

    /** Creates a draft invoice and makes the repository return it by its ID. */
    private function storedInvoice(ProductLine ...$productLines): Invoice
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com', ...$productLines);

        $this->invoiceRepository->method('findById')
            ->with($this->callback(static fn ($id): bool => $invoice->id->equals($id)))
            ->willReturn($invoice);

        return $invoice;
    }
}
