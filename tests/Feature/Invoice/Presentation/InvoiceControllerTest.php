<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Presentation;

use Modules\Invoices\Application\Outbox\InvoiceNotificationOutboxInterface;
use Modules\Invoices\Application\Outbox\OutboxStatus;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Modules\Invoices\Infrastructure\Eloquent\NotificationOutbox;
use Modules\Notifications\Api\NotificationFacadeInterface;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use Tests\TestCase;

final class InvoiceControllerTest extends TestCase
{
    #[Test]
    public function shouldCreateInvoiceWithProductLines(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'product_lines' => [
                ['name' => 'Widget', 'quantity' => 2, 'unit_price' => 300],
                ['name' => 'Gadget', 'quantity' => 1, 'unit_price' => 700],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.customer_name', 'John Doe')
            ->assertJsonPath('data.customer_email', 'john@example.com')
            ->assertJsonCount(2, 'data.product_lines')
            ->assertJsonPath('data.product_lines.0.total_unit_price', 600)
            ->assertJsonPath('data.total_price', 1300);
    }

    #[Test]
    public function shouldCreateInvoiceWithoutProductLines(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer_name' => 'Jane',
            'customer_email' => 'jane@example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.product_lines', [])
            ->assertJsonPath('data.total_price', 0);
    }

    #[Test]
    public function shouldRejectCreateWithMissingFields(): void
    {
        $response = $this->postJson('/api/invoices', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name', 'customer_email']);
    }

    #[Test]
    public function shouldRejectCreateWithInvalidProductLineQuantity(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer_name' => 'John',
            'customer_email' => 'john@example.com',
            'product_lines' => [
                ['name' => 'Widget', 'quantity' => 0, 'unit_price' => 100],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['product_lines.0.quantity']);
    }

    #[Test]
    public function shouldRejectCreateWithInvalidProductLineUnitPrice(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer_name' => 'John',
            'customer_email' => 'john@example.com',
            'product_lines' => [
                ['name' => 'Widget', 'quantity' => 1, 'unit_price' => -5],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['product_lines.0.unit_price']);
    }

    #[Test]
    public function shouldReturnInvoiceWithTotals(): void
    {
        $invoiceId = $this->createInvoiceViaApi([
            ['name' => 'Widget', 'quantity' => 2, 'unit_price' => 300],
        ]);

        $response = $this->getJson("/api/invoices/{$invoiceId}");

        $response->assertOk()
            ->assertJsonPath('data.id', $invoiceId)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.product_lines.0.total_unit_price', 600)
            ->assertJsonPath('data.total_price', 600);
    }

    #[Test]
    public function shouldReturn404ForNonExistentInvoice(): void
    {
        $response = $this->getJson('/api/invoices/'.Uuid::uuid4()->toString());

        $response->assertNotFound();
    }

    #[Test]
    public function shouldSendInvoiceAndTransitionToSending(): void
    {
        $invoiceId = $this->createInvoiceViaApi([
            ['name' => 'Widget', 'quantity' => 1, 'unit_price' => 100],
        ]);

        $response = $this->postJson("/api/invoices/{$invoiceId}/send");

        $response->assertOk()
            ->assertJsonPath('data.status', 'sending');
    }

    #[Test]
    public function shouldRejectSendingInvoiceWithoutProductLines(): void
    {
        $invoiceId = $this->createInvoiceViaApi();

        $response = $this->postJson("/api/invoices/{$invoiceId}/send");

        $response->assertUnprocessable();
    }

    #[Test]
    public function shouldNotNotifyAgainWhenSendingIsRepeated(): void
    {
        $invoiceId = $this->createInvoiceViaApi([
            ['name' => 'Widget', 'quantity' => 1, 'unit_price' => 100],
        ]);

        $this->mock(NotificationFacadeInterface::class, function ($mock): void {
            $mock->shouldReceive('notify')->once();
        });

        $this->postJson("/api/invoices/{$invoiceId}/send")->assertOk();
        $this->postJson("/api/invoices/{$invoiceId}/send")
            ->assertOk()
            ->assertJsonPath('data.status', 'sending');
    }

    #[Test]
    public function shouldRejectSendingAlreadySentInvoice(): void
    {
        $invoiceId = $this->createInvoiceViaApi([
            ['name' => 'Widget', 'quantity' => 1, 'unit_price' => 100],
        ]);

        $this->postJson("/api/invoices/{$invoiceId}/send")->assertOk();

        $this->getJson(route('notification.hook', [
            'action' => 'delivered',
            'reference' => $invoiceId,
        ]))->assertNoContent();

        $response = $this->postJson("/api/invoices/{$invoiceId}/send");

        $response->assertUnprocessable();
    }

    #[Test]
    public function shouldRejectCreateWhenProductLineTotalOverflows(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer_name' => 'John',
            'customer_email' => 'john@example.com',
            'product_lines' => [
                ['name' => 'Widget', 'quantity' => 2, 'unit_price' => PHP_INT_MAX],
            ],
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('invoices', 0);
    }

    #[Test]
    public function shouldKeepDraftWhenNotificationFails(): void
    {
        $invoiceId = $this->createInvoiceViaApi([
            ['name' => 'Widget', 'quantity' => 1, 'unit_price' => 100],
        ]);

        $this->mock(NotificationFacadeInterface::class, function ($mock): void {
            $mock->shouldReceive('notify')->once()->andThrow(new RuntimeException('delivery failed'));
        });

        $this->postJson("/api/invoices/{$invoiceId}/send")
            ->assertInternalServerError();

        $invoice = $this->app->make(InvoiceRepositoryInterface::class)
            ->get(Uuid::fromString($invoiceId));

        $this->assertSame(StatusEnum::Draft, $invoice->status());
    }

    #[Test]
    public function shouldCompleteFullInvoiceLifecycle(): void
    {
        $invoiceId = $this->createInvoiceViaApi([
            ['name' => 'Widget', 'quantity' => 2, 'unit_price' => 500],
        ]);

        $this->postJson("/api/invoices/{$invoiceId}/send")
            ->assertOk()
            ->assertJsonPath('data.status', 'sending');

        $this->getJson(route('notification.hook', [
            'action' => 'delivered',
            'reference' => $invoiceId,
        ]))->assertNoContent();

        $this->getJson("/api/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent-to-client');
    }

    #[Test]
    public function shouldDeliverPendingOutboxFromCommand(): void
    {
        $invoiceId = $this->createInvoiceViaApi([
            ['name' => 'Widget', 'quantity' => 1, 'unit_price' => 100],
        ]);

        $id = Uuid::fromString($invoiceId);
        $repository = $this->app->make(InvoiceRepositoryInterface::class);
        $invoice = $repository->get($id);
        $invoice->markAsSending();
        $repository->save($invoice, StatusEnum::Draft);
        $this->app->make(InvoiceNotificationOutboxInterface::class)
            ->enqueuePending($id);

        $this->mock(NotificationFacadeInterface::class, function ($mock): void {
            $mock->shouldReceive('notify')->once();
        });

        $this->artisan('invoices:dispatch-pending-notifications')->assertSuccessful();
    }

    #[Test]
    public function shouldKeepSentToClientWhenWebhookArrivesDuringFailedNotify(): void
    {
        $invoiceId = $this->createInvoiceViaApi([
            ['name' => 'Widget', 'quantity' => 1, 'unit_price' => 100],
        ]);

        $this->mock(NotificationFacadeInterface::class, function ($mock) use ($invoiceId): void {
            $mock->shouldReceive('notify')->once()->andReturnUsing(function () use ($invoiceId): void {
                $this->getJson(route('notification.hook', [
                    'action' => 'delivered',
                    'reference' => $invoiceId,
                ]))->assertNoContent();

                throw new RuntimeException('delivery failed');
            });
        });

        $this->postJson("/api/invoices/{$invoiceId}/send")
            ->assertInternalServerError();

        $id = Uuid::fromString($invoiceId);
        $invoice = $this->app->make(InvoiceRepositoryInterface::class)->get($id);
        $this->assertSame(StatusEnum::SentToClient, $invoice->status());
        $this->assertSame(
            OutboxStatus::Notified->value,
            NotificationOutbox::query()->where('invoice_id', $invoiceId)->value('status'),
        );
    }

    #[Test]
    public function shouldContinueCommandAfterOneDeliveryFailure(): void
    {
        $firstId = $this->prepareSendingWithPendingOutbox();
        $secondId = $this->prepareSendingWithPendingOutbox();

        $this->mock(NotificationFacadeInterface::class, function ($mock): void {
            $mock->shouldReceive('notify')->once()->ordered()->andThrow(new RuntimeException('delivery failed'));
            $mock->shouldReceive('notify')->once()->ordered();
        });

        $this->artisan('invoices:dispatch-pending-notifications')->assertFailed();

        $this->assertSame(
            StatusEnum::Draft,
            $this->app->make(InvoiceRepositoryInterface::class)->get($firstId)->status(),
        );
        $this->assertSame(
            OutboxStatus::Failed->value,
            NotificationOutbox::query()->where('invoice_id', $firstId->toString())->value('status'),
        );
        $this->assertSame(
            OutboxStatus::Notified->value,
            NotificationOutbox::query()->where('invoice_id', $secondId->toString())->value('status'),
        );
    }

    /** @param array<int, array<string, mixed>> $productLines */
    private function createInvoiceViaApi(array $productLines = []): string
    {
        $payload = [
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
        ];

        if ($productLines !== []) {
            $payload['product_lines'] = $productLines;
        }

        $response = $this->postJson('/api/invoices', $payload);
        $response->assertCreated();

        return $response->json('data.id');
    }

    private function prepareSendingWithPendingOutbox(): UuidInterface
    {
        $id = Uuid::uuid4();
        $repository = $this->app->make(InvoiceRepositoryInterface::class);
        $repository->save(Invoice::create($id, 'John Doe', 'john@example.com', [
            new ProductLine('Widget', 1, 100),
        ]));
        $invoice = $repository->get($id);
        $invoice->markAsSending();
        $repository->save($invoice, StatusEnum::Draft);
        $this->app->make(InvoiceNotificationOutboxInterface::class)->enqueuePending($id);

        return $id;
    }
}
