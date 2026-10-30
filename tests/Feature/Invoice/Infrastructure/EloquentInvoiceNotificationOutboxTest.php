<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Infrastructure;

use Modules\Invoices\Application\Outbox\InvoiceNotificationOutboxInterface;
use Modules\Invoices\Application\Outbox\OutboxStatus;
use Modules\Invoices\Application\Services\InvoiceNotificationDelivery;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Modules\Invoices\Infrastructure\Eloquent\NotificationOutbox;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Tests\TestCase;

final class EloquentInvoiceNotificationOutboxTest extends TestCase
{
    private InvoiceNotificationOutboxInterface $outbox;

    private InvoiceRepositoryInterface $invoices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outbox = $this->app->make(InvoiceNotificationOutboxInterface::class);
        $this->invoices = $this->app->make(InvoiceRepositoryInterface::class);
    }

    #[Test]
    public function shouldExcludeFailedFromDueIds(): void
    {
        $id = $this->createDraftInvoice();
        $this->outbox->enqueuePending($id);
        $token = $this->outbox->claim($id, now()->subSeconds(InvoiceNotificationDelivery::STALE_AFTER_SECONDS));
        $this->assertNotNull($token);
        $this->outbox->markFailed($id, $token);

        $this->assertSame([], $this->outbox->idsDueForDelivery(now()->subSeconds(InvoiceNotificationDelivery::STALE_AFTER_SECONDS)));
    }

    #[Test]
    public function shouldExcludeFreshProcessingFromDueIds(): void
    {
        $id = $this->createDraftInvoice();
        $this->outbox->enqueuePending($id);
        $this->assertNotNull($this->outbox->claim($id, now()->subSeconds(InvoiceNotificationDelivery::STALE_AFTER_SECONDS)));

        $this->assertSame([], $this->outbox->idsDueForDelivery(now()->subSeconds(InvoiceNotificationDelivery::STALE_AFTER_SECONDS)));
    }

    #[Test]
    public function shouldIncludeStaleProcessingInDueIds(): void
    {
        $id = $this->createDraftInvoice();
        $this->outbox->enqueuePending($id);
        $this->assertNotNull($this->outbox->claim($id, now()->subSeconds(InvoiceNotificationDelivery::STALE_AFTER_SECONDS)));

        NotificationOutbox::query()->where('invoice_id', $id->toString())->update([
            'claimed_at' => now()->subSeconds(InvoiceNotificationDelivery::STALE_AFTER_SECONDS + 1),
        ]);

        $due = $this->outbox->idsDueForDelivery(now()->subSeconds(InvoiceNotificationDelivery::STALE_AFTER_SECONDS));

        $this->assertCount(1, $due);
        $this->assertTrue($id->equals($due[0]));
    }

    #[Test]
    public function shouldClaimFailedForManualRetry(): void
    {
        $id = $this->createDraftInvoice();
        $this->outbox->enqueuePending($id);
        $token = $this->outbox->claim($id, now()->subSeconds(InvoiceNotificationDelivery::STALE_AFTER_SECONDS));
        $this->assertNotNull($token);
        $this->outbox->markFailed($id, $token);

        $retry = $this->outbox->claim($id, now()->subSeconds(InvoiceNotificationDelivery::STALE_AFTER_SECONDS));

        $this->assertNotNull($retry);
        $this->assertTrue($this->outbox->ownsClaim($id, $retry));
    }

    #[Test]
    public function shouldRevertSendingOnlyWithOwnProcessingToken(): void
    {
        $id = $this->createSendingInvoice();
        $this->outbox->enqueuePending($id);
        $token = $this->outbox->claim($id, now()->subSeconds(InvoiceNotificationDelivery::STALE_AFTER_SECONDS));
        $this->assertNotNull($token);

        $this->assertTrue($this->outbox->revertSendingIfClaimHeld($id, $token));
        $this->assertSame(StatusEnum::Draft, $this->invoices->get($id)->status());
        $this->assertSame(
            OutboxStatus::Failed->value,
            NotificationOutbox::query()->where('invoice_id', $id->toString())->value('status'),
        );
    }

    #[Test]
    public function shouldNotRevertSendingWithForeignToken(): void
    {
        $id = $this->createSendingInvoice();
        $this->outbox->enqueuePending($id);
        $token = $this->outbox->claim($id, now()->subSeconds(InvoiceNotificationDelivery::STALE_AFTER_SECONDS));
        $this->assertNotNull($token);

        $this->assertFalse($this->outbox->revertSendingIfClaimHeld($id, Uuid::uuid4()->toString()));
        $this->assertSame(StatusEnum::Sending, $this->invoices->get($id)->status());
        $this->assertTrue($this->outbox->ownsClaim($id, $token));
    }

    #[Test]
    public function shouldNotRevertSendingWhenOutboxIsNotified(): void
    {
        $id = $this->createSendingInvoice();
        $this->outbox->enqueuePending($id);
        $token = $this->outbox->claim($id, now()->subSeconds(InvoiceNotificationDelivery::STALE_AFTER_SECONDS));
        $this->assertNotNull($token);
        $this->outbox->markNotified($id, $token);

        $this->assertFalse($this->outbox->revertSendingIfClaimHeld($id, $token));
        $this->assertSame(StatusEnum::Sending, $this->invoices->get($id)->status());
        $this->assertSame(
            OutboxStatus::Notified->value,
            NotificationOutbox::query()->where('invoice_id', $id->toString())->value('status'),
        );
    }

    #[Test]
    public function shouldNotMarkNotifiedWithForeignToken(): void
    {
        $id = $this->createDraftInvoice();
        $this->outbox->enqueuePending($id);
        $token = $this->outbox->claim($id, now()->subSeconds(InvoiceNotificationDelivery::STALE_AFTER_SECONDS));
        $this->assertNotNull($token);

        $this->assertFalse($this->outbox->markNotified($id, Uuid::uuid4()->toString()));
        $this->assertTrue($this->outbox->ownsClaim($id, $token));
        $this->assertSame(
            OutboxStatus::Processing->value,
            NotificationOutbox::query()->where('invoice_id', $id->toString())->value('status'),
        );
    }

    private function createDraftInvoice(): UuidInterface
    {
        $id = Uuid::uuid4();
        $this->invoices->save(Invoice::create($id, 'Jane', 'jane@example.com', [
            new ProductLine('Widget', 1, 100),
        ]));

        return $id;
    }

    private function createSendingInvoice(): UuidInterface
    {
        $id = $this->createDraftInvoice();
        $invoice = $this->invoices->get($id);
        $invoice->markAsSending();
        $this->invoices->save($invoice, StatusEnum::Draft);

        return $id;
    }
}
