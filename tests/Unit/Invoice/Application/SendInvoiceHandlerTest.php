<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Application;

use Modules\Invoices\Application\Commands\SendInvoiceCommand;
use Modules\Invoices\Application\Handlers\SendInvoiceHandler;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\EmptyProductLinesException;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class SendInvoiceHandlerTest extends TestCase
{
    #[Test]
    public function shouldPersistSendingBeforeNotificationAndNotSaveAgain(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::create($id, 'John', 'john@example.com', [
            new ProductLine('Widget', 1, 100),
        ]);

        $repository = $this->repositoryThatRunsTransactions();
        $repository->method('get')->with($id, true)->willReturn($invoice);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(
                fn (Invoice $inv): bool => $inv->status() === StatusEnum::Sending,
            ));

        $saveBeforeNotify = false;
        $facade = $this->createMock(NotificationFacadeInterface::class);
        $facade->expects($this->once())
            ->method('notify')
            ->with($this->callback(
                function (NotifyData $data) use ($id, $invoice, &$saveBeforeNotify): bool {
                    $saveBeforeNotify = $invoice->status() === StatusEnum::Sending;

                    return $data->resourceId->equals($id)
                        && $data->toEmail === 'john@example.com';
                },
            ));

        $handler = new SendInvoiceHandler($repository, $facade);
        $handler->handle(new SendInvoiceCommand($id));

        $this->assertTrue($saveBeforeNotify);
        $this->assertSame(StatusEnum::Sending, $invoice->status());
    }

    #[Test]
    public function shouldRevertToDraftWhenNotificationFails(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::create($id, 'John', 'john@example.com', [
            new ProductLine('Widget', 1, 100),
        ]);

        $repository = $this->repositoryThatRunsTransactions();
        $repository->method('get')->willReturnCallback(
            function () use ($invoice): Invoice {
                return $invoice;
            },
        );
        $savedStatuses = [];
        $repository->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (Invoice $inv) use (&$savedStatuses): void {
                $savedStatuses[] = $inv->status();
            });

        $facade = $this->createMock(NotificationFacadeInterface::class);
        $facade->method('notify')->willThrowException(new RuntimeException('delivery failed'));

        $handler = new SendInvoiceHandler($repository, $facade);

        try {
            $handler->handle(new SendInvoiceCommand($id));
            $this->fail('Expected notification failure to be rethrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('delivery failed', $e->getMessage());
        }

        $this->assertSame([StatusEnum::Sending, StatusEnum::Draft], $savedStatuses);
        $this->assertSame(StatusEnum::Draft, $invoice->status());
    }

    #[Test]
    public function shouldNotRevertWhenStatusIsNoLongerSendingAfterNotificationFailure(): void
    {
        $id = Uuid::uuid4();
        $draft = Invoice::create($id, 'John', 'john@example.com', [
            new ProductLine('Widget', 1, 100),
        ]);
        $delivered = Invoice::reconstitute(
            $id,
            'John',
            'john@example.com',
            StatusEnum::SentToClient,
            [new ProductLine('Widget', 1, 100)],
        );

        $repository = $this->repositoryThatRunsTransactions();
        $repository->method('get')->willReturnOnConsecutiveCalls($draft, $delivered);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(
                fn (Invoice $inv): bool => $inv->status() === StatusEnum::Sending,
            ));

        $facade = $this->createMock(NotificationFacadeInterface::class);
        $facade->method('notify')->willThrowException(new RuntimeException('delivery failed'));

        $handler = new SendInvoiceHandler($repository, $facade);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('delivery failed');
        $handler->handle(new SendInvoiceCommand($id));
    }

    #[Test]
    public function shouldRejectSendingWithoutProductLines(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::create($id, 'John', 'john@example.com');

        $repository = $this->repositoryThatRunsTransactions();
        $repository->method('get')->willReturn($invoice);
        $repository->expects($this->never())->method('save');

        $facade = $this->createMock(NotificationFacadeInterface::class);
        $facade->expects($this->never())->method('notify');

        $handler = new SendInvoiceHandler($repository, $facade);

        $this->expectException(EmptyProductLinesException::class);
        $handler->handle(new SendInvoiceCommand($id));
    }

    #[Test]
    public function shouldRejectSendingNonDraftInvoice(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::reconstitute(
            $id, 'John', 'john@example.com',
            StatusEnum::Sending,
            [new ProductLine('Widget', 1, 100)],
        );

        $repository = $this->repositoryThatRunsTransactions();
        $repository->method('get')->willReturn($invoice);
        $repository->expects($this->never())->method('save');

        $facade = $this->createMock(NotificationFacadeInterface::class);
        $facade->expects($this->never())->method('notify');

        $handler = new SendInvoiceHandler($repository, $facade);

        $this->expectException(InvalidStatusTransitionException::class);
        $handler->handle(new SendInvoiceCommand($id));
    }

    /** @return InvoiceRepositoryInterface&MockObject */
    private function repositoryThatRunsTransactions(): InvoiceRepositoryInterface
    {
        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->method('transaction')->willReturnCallback(
            fn (callable $callback): mixed => $callback(),
        );

        return $repository;
    }
}
