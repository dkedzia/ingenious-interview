<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Presentation;

use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
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
        $response = $this->getJson('/api/invoices/' . Uuid::uuid4()->toString());

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
    public function shouldRejectSendingAlreadySentInvoice(): void
    {
        $invoiceId = $this->createInvoiceViaApi([
            ['name' => 'Widget', 'quantity' => 1, 'unit_price' => 100],
        ]);

        $this->postJson("/api/invoices/{$invoiceId}/send")->assertOk();

        $response = $this->postJson("/api/invoices/{$invoiceId}/send");

        $response->assertUnprocessable();
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
}
