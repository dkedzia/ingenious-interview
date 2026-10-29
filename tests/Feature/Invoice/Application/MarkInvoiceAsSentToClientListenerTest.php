<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Application;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class MarkInvoiceAsSentToClientListenerTest extends TestCase
{
    private InvoiceRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->app->make(InvoiceRepositoryInterface::class);
    }

    #[Test]
    public function shouldTransitionToSentToClientWhenDelivered(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::create($id, 'John', 'john@example.com', [
            new ProductLine('Widget', 1, 100),
        ]);
        $invoice->markAsSending();
        $this->repository->save($invoice);

        event(new WebhookDeliveredEvent(resourceId: $id));

        $updated = $this->repository->get($id);
        $this->assertSame(StatusEnum::SentToClient, $updated->status());
    }

    #[Test]
    public function shouldIgnoreDeliveryWhenNotInSendingStatus(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::create($id, 'John', 'john@example.com', [
            new ProductLine('Widget', 1, 100),
        ]);
        $this->repository->save($invoice);

        event(new WebhookDeliveredEvent(resourceId: $id));

        $unchanged = $this->repository->get($id);
        $this->assertSame(StatusEnum::Draft, $unchanged->status());
    }

    #[Test]
    public function shouldIgnoreDeliveryWhenInvoiceNotFound(): void
    {
        event(new WebhookDeliveredEvent(resourceId: Uuid::uuid4()));

        $this->assertTrue(true);
    }
}
