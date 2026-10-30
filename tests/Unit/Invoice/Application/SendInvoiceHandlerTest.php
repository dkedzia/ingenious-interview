<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Application;

use Modules\Invoices\Application\Commands\SendInvoiceCommand;
use Modules\Invoices\Application\Handlers\SendInvoiceHandler;
use Modules\Invoices\Application\Outbox\InvoiceNotificationOutboxInterface;
use Modules\Invoices\Application\Services\InvoiceNotificationDeliveryInterface;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\ConcurrentStatusChangeException;
use Modules\Invoices\Domain\Exceptions\EmptyProductLinesException;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class SendInvoiceHandlerTest extends TestCase
{
    #[Test]
    public function shouldPersistSendingAndOutboxBeforeDelivery(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::create($id, 'John', 'john@example.com', [
            new ProductLine('Widget', 1, 100),
        ]);

        $saveBeforeEnqueue = false;
        $enqueueBeforeDeliver = false;

        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->method('get')->with($id)->willReturn($invoice);
        $repository->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(fn (Invoice $inv): bool => $inv->status() === StatusEnum::Sending),
                StatusEnum::Draft,
            )
            ->willReturnCallback(function () use (&$saveBeforeEnqueue): void {
                $saveBeforeEnqueue = true;
            });

        $outbox = $this->createMock(InvoiceNotificationOutboxInterface::class);
        $outbox->expects($this->once())
            ->method('enqueuePending')
            ->with($id)
            ->willReturnCallback(function () use (&$saveBeforeEnqueue, &$enqueueBeforeDeliver): void {
                $this->assertTrue($saveBeforeEnqueue);
                $enqueueBeforeDeliver = true;
            });

        $delivery = $this->createMock(InvoiceNotificationDeliveryInterface::class);
        $delivery->expects($this->once())
            ->method('deliver')
            ->with($id)
            ->willReturnCallback(function () use (&$enqueueBeforeDeliver): void {
                $this->assertTrue($enqueueBeforeDeliver);
            });

        $handler = new SendInvoiceHandler($repository, $outbox, $delivery);
        $handler->handle(new SendInvoiceCommand($id));
    }

    #[Test]
    public function shouldDeliverWhenAlreadySendingAndNotNotified(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::reconstitute(
            $id,
            'John',
            'john@example.com',
            StatusEnum::Sending,
            [new ProductLine('Widget', 1, 100)],
        );

        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->method('get')->willReturn($invoice);
        $repository->expects($this->never())->method('save');

        $outbox = $this->createMock(InvoiceNotificationOutboxInterface::class);
        $outbox->method('isNotified')->with($id)->willReturn(false);
        $outbox->expects($this->never())->method('enqueuePending');

        $delivery = $this->createMock(InvoiceNotificationDeliveryInterface::class);
        $delivery->expects($this->once())->method('deliver')->with($id);

        $handler = new SendInvoiceHandler($repository, $outbox, $delivery);
        $handler->handle(new SendInvoiceCommand($id));
    }

    #[Test]
    public function shouldNotDeliverWhenAlreadyNotified(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::reconstitute(
            $id,
            'John',
            'john@example.com',
            StatusEnum::Sending,
            [new ProductLine('Widget', 1, 100)],
        );

        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->method('get')->willReturn($invoice);

        $outbox = $this->createMock(InvoiceNotificationOutboxInterface::class);
        $outbox->method('isNotified')->with($id)->willReturn(true);

        $delivery = $this->createMock(InvoiceNotificationDeliveryInterface::class);
        $delivery->expects($this->never())->method('deliver');

        $handler = new SendInvoiceHandler($repository, $outbox, $delivery);
        $handler->handle(new SendInvoiceCommand($id));
    }

    #[Test]
    public function shouldRejectSendingSentToClientInvoice(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::reconstitute(
            $id,
            'John',
            'john@example.com',
            StatusEnum::SentToClient,
            [new ProductLine('Widget', 1, 100)],
        );

        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->method('get')->willReturn($invoice);
        $repository->expects($this->never())->method('save');

        $outbox = $this->createMock(InvoiceNotificationOutboxInterface::class);
        $outbox->expects($this->never())->method('enqueuePending');

        $delivery = $this->createMock(InvoiceNotificationDeliveryInterface::class);
        $delivery->expects($this->never())->method('deliver');

        $handler = new SendInvoiceHandler($repository, $outbox, $delivery);

        $this->expectException(InvalidStatusTransitionException::class);
        $handler->handle(new SendInvoiceCommand($id));
    }

    #[Test]
    public function shouldDeliverWhenLostSendRaceLeavesSending(): void
    {
        $id = Uuid::uuid4();
        $draft = Invoice::create($id, 'John', 'john@example.com', [
            new ProductLine('Widget', 1, 100),
        ]);
        $sending = Invoice::reconstitute(
            $id,
            'John',
            'john@example.com',
            StatusEnum::Sending,
            [new ProductLine('Widget', 1, 100)],
        );

        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->method('get')->willReturnOnConsecutiveCalls($draft, $sending);
        $repository->method('save')->willThrowException(ConcurrentStatusChangeException::forInvoice($id));

        $outbox = $this->createMock(InvoiceNotificationOutboxInterface::class);
        $outbox->method('isNotified')->willReturn(false);
        $outbox->expects($this->never())->method('enqueuePending');

        $delivery = $this->createMock(InvoiceNotificationDeliveryInterface::class);
        $delivery->expects($this->once())->method('deliver')->with($id);

        $handler = new SendInvoiceHandler($repository, $outbox, $delivery);
        $handler->handle(new SendInvoiceCommand($id));
    }

    #[Test]
    public function shouldRetryWhenLostSendRaceReturnsToDraft(): void
    {
        $id = Uuid::uuid4();
        $first = Invoice::create($id, 'John', 'john@example.com', [
            new ProductLine('Widget', 1, 100),
        ]);
        $second = Invoice::create($id, 'John', 'john@example.com', [
            new ProductLine('Widget', 1, 100),
        ]);

        $saves = 0;
        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->method('get')->willReturnOnConsecutiveCalls($first, $second);
        $repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function () use (&$saves, $id): void {
                $saves++;
                if ($saves === 1) {
                    throw ConcurrentStatusChangeException::forInvoice($id);
                }
            });

        $outbox = $this->createMock(InvoiceNotificationOutboxInterface::class);
        $outbox->expects($this->once())->method('enqueuePending')->with($id);

        $delivery = $this->createMock(InvoiceNotificationDeliveryInterface::class);
        $delivery->expects($this->once())->method('deliver')->with($id);

        $handler = new SendInvoiceHandler($repository, $outbox, $delivery);
        $handler->handle(new SendInvoiceCommand($id));
    }

    #[Test]
    public function shouldRejectWhenLostSendRaceLeavesSentToClient(): void
    {
        $id = Uuid::uuid4();
        $draft = Invoice::create($id, 'John', 'john@example.com', [
            new ProductLine('Widget', 1, 100),
        ]);
        $sent = Invoice::reconstitute(
            $id,
            'John',
            'john@example.com',
            StatusEnum::SentToClient,
            [new ProductLine('Widget', 1, 100)],
        );

        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->method('get')->willReturnOnConsecutiveCalls($draft, $sent);
        $repository->method('save')->willThrowException(ConcurrentStatusChangeException::forInvoice($id));

        $outbox = $this->createMock(InvoiceNotificationOutboxInterface::class);
        $outbox->expects($this->never())->method('enqueuePending');

        $delivery = $this->createMock(InvoiceNotificationDeliveryInterface::class);
        $delivery->expects($this->never())->method('deliver');

        $handler = new SendInvoiceHandler($repository, $outbox, $delivery);

        $this->expectException(InvalidStatusTransitionException::class);
        $handler->handle(new SendInvoiceCommand($id));
    }

    #[Test]
    public function shouldRejectSendingWithoutProductLines(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::create($id, 'John', 'john@example.com');

        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->method('get')->willReturn($invoice);
        $repository->expects($this->never())->method('save');

        $outbox = $this->createMock(InvoiceNotificationOutboxInterface::class);
        $outbox->expects($this->never())->method('enqueuePending');

        $delivery = $this->createMock(InvoiceNotificationDeliveryInterface::class);
        $delivery->expects($this->never())->method('deliver');

        $handler = new SendInvoiceHandler($repository, $outbox, $delivery);

        $this->expectException(EmptyProductLinesException::class);
        $handler->handle(new SendInvoiceCommand($id));
    }
}
