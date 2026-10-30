<?php

declare(strict_types=1);

namespace Tests\Unit\Invoice\Application;

use Modules\Invoices\Application\Listeners\MarkInvoiceAsSentToClientListener;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\ConcurrentStatusChangeException;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class MarkInvoiceAsSentToClientListenerTest extends TestCase
{
    #[Test]
    public function shouldSwallowConcurrentStatusChangeOnSave(): void
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
        $repository->method('find')->willReturn($invoice);
        $repository->expects($this->once())
            ->method('save')
            ->willThrowException(ConcurrentStatusChangeException::forInvoice($id));

        $listener = new MarkInvoiceAsSentToClientListener($repository);
        $listener->handle(new WebhookDeliveredEvent(resourceId: $id));
    }
}
