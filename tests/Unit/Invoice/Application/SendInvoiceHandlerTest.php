<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Application;

use Modules\Invoices\Application\Commands\SendInvoiceCommand;
use Modules\Invoices\Application\Handlers\SendInvoiceHandler;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\EmptyProductLinesException;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Models\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class SendInvoiceHandlerTest extends TestCase
{
    #[Test]
    public function shouldSendNotificationAndTransitionToSending(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::create($id, 'John', 'john@example.com', [
            new ProductLine('Widget', 1, 100),
        ]);

        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->method('get')->with($id)->willReturn($invoice);
        $repository->expects($this->once())
            ->method('save')
            ->with($this->callback(
                fn (Invoice $inv): bool => $inv->status() === StatusEnum::Sending,
            ));

        $facade = $this->createMock(NotificationFacadeInterface::class);
        $facade->expects($this->once())
            ->method('notify')
            ->with($this->callback(
                fn (NotifyData $data): bool => $data->resourceId->equals($id)
                    && $data->toEmail === 'john@example.com',
            ));

        $handler = new SendInvoiceHandler($repository, $facade);
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

        $repository = $this->createMock(InvoiceRepositoryInterface::class);
        $repository->method('get')->willReturn($invoice);
        $repository->expects($this->never())->method('save');

        $facade = $this->createMock(NotificationFacadeInterface::class);
        $facade->expects($this->never())->method('notify');

        $handler = new SendInvoiceHandler($repository, $facade);

        $this->expectException(InvalidStatusTransitionException::class);
        $handler->handle(new SendInvoiceCommand($id));
    }
}
