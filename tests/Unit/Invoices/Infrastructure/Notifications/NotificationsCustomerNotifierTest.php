<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Infrastructure\Notifications;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Infrastructure\Notifications\NotificationsCustomerNotifier;
use Modules\Invoices\UseCase\SendInvoice\CustomerNotNotifiedException;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class NotificationsCustomerNotifierTest extends TestCase
{
    public function test_it_emails_the_customer_with_the_invoice_id_as_the_reference(): void
    {
        $invoice = $this->createInvoice();

        $notificationFacade = $this->createMock(NotificationFacadeInterface::class);
        $notificationFacade->expects($this->once())
            ->method('notify')
            ->with($this->callback(static fn (NotifyData $data): bool => $data->resourceId->equals($invoice->id)
                && $data->toEmail === 'jane.doe@example.com'
                && $data->subject === sprintf('Invoice %s', $invoice->id->toString())
                && $data->message === 'Dear Jane Doe, your invoice for a total of 300 is ready.'));

        new NotificationsCustomerNotifier($notificationFacade)->notifyInvoiceIssued($invoice);
    }

    public function test_a_failed_notification_is_reported(): void
    {
        $invoice = $this->createInvoice();
        $cause = new RuntimeException('Provider unavailable.');

        $notificationFacade = $this->createStub(NotificationFacadeInterface::class);
        $notificationFacade->method('notify')->willThrowException($cause);

        try {
            new NotificationsCustomerNotifier($notificationFacade)->notifyInvoiceIssued($invoice);

            $this->fail('Expected the customer not to be notified.');
        } catch (CustomerNotNotifiedException $exception) {
            $this->assertTrue($exception->invoiceId->equals($invoice->id));
            $this->assertSame($cause, $exception->getPrevious());
        }
    }

    private function createInvoice(): Invoice
    {
        return new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com', new ProductLine('Keyboard', 2, 150));
    }
}
