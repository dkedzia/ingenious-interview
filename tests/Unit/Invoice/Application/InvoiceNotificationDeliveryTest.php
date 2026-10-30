<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Application;

use Modules\Invoices\Application\Exceptions\SendInvoiceCompensationException;
use Modules\Invoices\Application\Outbox\InvoiceNotificationOutboxInterface;
use Modules\Invoices\Application\Services\InvoiceNotificationDelivery;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Modules\Notifications\Api\NotificationFacadeInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class InvoiceNotificationDeliveryTest extends TestCase
{
    private const TOKEN = 'claim-token';

    #[Test]
    public function shouldSkipNotifyWhenClaimLoses(): void
    {
        $id = Uuid::uuid4();

        $outbox = $this->createMock(InvoiceNotificationOutboxInterface::class);
        $outbox->method('claim')->willReturn(null);

        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->expects($this->never())->method('get');

        $notifications = $this->createMock(NotificationFacadeInterface::class);
        $notifications->expects($this->never())->method('notify');

        $delivery = new InvoiceNotificationDelivery($invoices, $outbox, $notifications);
        $delivery->deliver($id);
    }

    #[Test]
    public function shouldNotifyAndMarkNotifiedWhenClaimSucceeds(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::reconstitute(
            $id,
            'John',
            'john@example.com',
            StatusEnum::Sending,
            [new ProductLine('Widget', 1, 100)],
        );

        $outbox = $this->createMock(InvoiceNotificationOutboxInterface::class);
        $outbox->method('claim')->willReturn(self::TOKEN);
        $outbox->expects($this->once())->method('markNotified')->with($id, self::TOKEN);

        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->method('get')->willReturn($invoice);

        $notifications = $this->createMock(NotificationFacadeInterface::class);
        $notifications->expects($this->once())->method('notify');

        $delivery = new InvoiceNotificationDelivery($invoices, $outbox, $notifications);
        $delivery->deliver($id);
    }

    #[Test]
    public function shouldRevertToDraftWhenNotificationFails(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::reconstitute(
            $id,
            'John',
            'john@example.com',
            StatusEnum::Sending,
            [new ProductLine('Widget', 1, 100)],
        );

        $outbox = $this->createMock(InvoiceNotificationOutboxInterface::class);
        $outbox->method('claim')->willReturn(self::TOKEN);
        $outbox->expects($this->once())->method('revertSendingIfClaimHeld')->with($id, self::TOKEN)->willReturn(true);
        $outbox->expects($this->never())->method('markFailed');

        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->method('get')->willReturn($invoice);
        $invoices->expects($this->never())->method('save');

        $notifications = $this->createMock(NotificationFacadeInterface::class);
        $notifications->expects($this->once())->method('notify')->willThrowException(new RuntimeException('delivery failed'));

        $delivery = new InvoiceNotificationDelivery($invoices, $outbox, $notifications);

        try {
            $delivery->deliver($id);
            $this->fail('Expected notification failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('delivery failed', $e->getMessage());
        }
    }

    #[Test]
    public function shouldNotRevertWhenStatusIsNoLongerSendingAfterNotificationFailure(): void
    {
        $id = Uuid::uuid4();
        $sending = Invoice::reconstitute(
            $id,
            'John',
            'john@example.com',
            StatusEnum::Sending,
            [new ProductLine('Widget', 1, 100)],
        );
        $delivered = Invoice::reconstitute(
            $id,
            'John',
            'john@example.com',
            StatusEnum::SentToClient,
            [new ProductLine('Widget', 1, 100)],
        );

        $outbox = $this->createMock(InvoiceNotificationOutboxInterface::class);
        $outbox->method('claim')->willReturn(self::TOKEN);
        $outbox->method('revertSendingIfClaimHeld')->willReturn(false);
        $outbox->method('ownsClaim')->willReturn(true);
        $outbox->expects($this->once())->method('markNotified')->with($id, self::TOKEN);
        $outbox->expects($this->never())->method('markFailed');

        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->method('get')->willReturnOnConsecutiveCalls($sending, $delivered);
        $invoices->expects($this->never())->method('save');

        $notifications = $this->createMock(NotificationFacadeInterface::class);
        $notifications->method('notify')->willThrowException(new RuntimeException('delivery failed'));

        $delivery = new InvoiceNotificationDelivery($invoices, $outbox, $notifications);

        $this->expectException(RuntimeException::class);
        $delivery->deliver($id);
    }

    #[Test]
    public function shouldNotWriteInvoiceWhenClaimIsLostBeforeCompensation(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::reconstitute(
            $id,
            'John',
            'john@example.com',
            StatusEnum::Sending,
            [new ProductLine('Widget', 1, 100)],
        );

        $outbox = $this->createMock(InvoiceNotificationOutboxInterface::class);
        $outbox->method('claim')->willReturn(self::TOKEN);
        $outbox->method('revertSendingIfClaimHeld')->willReturn(false);
        $outbox->method('ownsClaim')->willReturn(false);
        $outbox->expects($this->never())->method('markFailed');
        $outbox->expects($this->never())->method('markNotified');

        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->method('get')->willReturn($invoice);
        $invoices->expects($this->never())->method('save');

        $notifications = $this->createMock(NotificationFacadeInterface::class);
        $notifications->method('notify')->willThrowException(new RuntimeException('delivery failed'));

        $delivery = new InvoiceNotificationDelivery($invoices, $outbox, $notifications);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('delivery failed');
        $delivery->deliver($id);
    }

    #[Test]
    public function shouldKeepNotificationFailureAsPreviousWhenCompensationFails(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::reconstitute(
            $id,
            'John',
            'john@example.com',
            StatusEnum::Sending,
            [new ProductLine('Widget', 1, 100)],
        );
        $notificationFailure = new RuntimeException('delivery failed');

        $outbox = $this->createMock(InvoiceNotificationOutboxInterface::class);
        $outbox->method('claim')->willReturn(self::TOKEN);
        $outbox->method('revertSendingIfClaimHeld')->willThrowException(new RuntimeException('database down'));

        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->method('get')->willReturn($invoice);
        $invoices->expects($this->never())->method('save');

        $notifications = $this->createMock(NotificationFacadeInterface::class);
        $notifications->method('notify')->willThrowException($notificationFailure);

        $delivery = new InvoiceNotificationDelivery($invoices, $outbox, $notifications);

        try {
            $delivery->deliver($id);
            $this->fail('Expected compensation failure.');
        } catch (SendInvoiceCompensationException $e) {
            $this->assertSame($notificationFailure, $e->getPrevious());
            $this->assertSame('database down', $e->compensationFailure->getMessage());
        }
    }

    #[Test]
    public function shouldMarkNotifiedWhenInvoiceAlreadySentToClient(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::reconstitute(
            $id,
            'John',
            'john@example.com',
            StatusEnum::SentToClient,
            [new ProductLine('Widget', 1, 100)],
        );

        $outbox = $this->createMock(InvoiceNotificationOutboxInterface::class);
        $outbox->method('claim')->willReturn(self::TOKEN);
        $outbox->expects($this->once())->method('markNotified')->with($id, self::TOKEN);

        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->method('get')->willReturn($invoice);

        $notifications = $this->createMock(NotificationFacadeInterface::class);
        $notifications->expects($this->never())->method('notify');

        $delivery = new InvoiceNotificationDelivery($invoices, $outbox, $notifications);
        $delivery->deliver($id);
    }
}
