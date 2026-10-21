<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\UseCase;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSentException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotStoredException;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\UseCase\Exceptions\InvoiceNotSentException;
use Modules\Invoices\UseCase\SendInvoice;
use Modules\Invoices\UseCase\SendInvoiceRequest;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class SendInvoiceTest extends TestCase
{
    private InvoiceRepositoryInterface&MockObject $invoiceRepository;

    private NotificationFacadeInterface&MockObject $notificationFacade;

    private SendInvoice $sendInvoice;

    protected function setUp(): void
    {
        $this->invoiceRepository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->notificationFacade = $this->createMock(NotificationFacadeInterface::class);

        $this->sendInvoice = new SendInvoice(
            $this->invoiceRepository,
            $this->notificationFacade,
        );
    }

    public function test_it_notifies_the_customer_and_saves_the_invoice_as_sending(): void
    {
        $invoice = $this->storedInvoice(new ProductLine('Keyboard', 2, 150));

        $this->notificationFacade->expects($this->once())
            ->method('notify')
            ->with($this->callback(static fn (NotifyData $data): bool => $data->resourceId->equals($invoice->id)
                && $data->toEmail === 'jane.doe@example.com'));

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

        $this->notificationFacade->method('notify')->willReturnCallback(static function () use (&$calls): void {
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

        $this->notificationFacade->expects($this->never())->method('notify');
        $this->invoiceRepository->expects($this->never())->method('save');

        $this->expectExceptionObject($failure);

        $this->sendInvoice->execute(new SendInvoiceRequest($invoiceId));
    }

    public function test_an_invoice_without_product_lines_is_not_sent(): void
    {
        $invoice = $this->storedInvoice();

        $this->notificationFacade->expects($this->never())->method('notify');
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

        $this->notificationFacade->expects($this->never())->method('notify');
        $this->invoiceRepository->expects($this->never())->method('save');

        $this->expectException(InvoiceCannotBeSentException::class);

        $this->sendInvoice->execute(new SendInvoiceRequest($invoice->id));
    }

    public function test_a_failed_notification_is_reported_and_the_invoice_is_not_saved(): void
    {
        $invoice = $this->storedInvoice(new ProductLine('Keyboard', 2, 150));
        $cause = new RuntimeException('Provider unavailable.');
        $this->notificationFacade->method('notify')->willThrowException($cause);

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
