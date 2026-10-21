<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices\Http;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Infrastructure\Persistence\Models\Invoice as InvoiceModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class CreateInvoiceEndpointTest extends TestCase
{
    public function test_it_creates_a_draft_invoice_and_returns_it(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane.doe@example.com',
            'product_lines' => [
                ['name' => 'Keyboard', 'quantity' => 2, 'unit_price' => 150],
                ['name' => 'Mouse', 'quantity' => 3, 'unit_price' => 10],
            ],
        ]);

        $response->assertCreated()->assertJson(['data' => [
            'status' => 'draft',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane.doe@example.com',
            'product_lines' => [
                ['name' => 'Keyboard', 'quantity' => 2, 'unit_price' => 150, 'total_unit_price' => 300],
                ['name' => 'Mouse', 'quantity' => 3, 'unit_price' => 10, 'total_unit_price' => 30],
            ],
            'total_price' => 330,
        ]]);

        $stored = $this->app->make(InvoiceRepositoryInterface::class)
            ->findById(Uuid::fromString($response->json('data.id')));
        $this->assertSame(StatusEnum::Draft, $stored->status);
        $this->assertSame(330, $stored->totalPrice());
    }

    public function test_it_creates_an_invoice_without_product_lines(): void
    {
        $this->postJson('/api/invoices', [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane.doe@example.com',
        ])->assertCreated()->assertJson(['data' => ['product_lines' => [], 'total_price' => 0]]);
    }

    public function test_the_caller_cannot_choose_the_id_status_or_totals(): void
    {
        $id = Uuid::uuid7()->toString();

        $this->postJson('/api/invoices', [
            'id' => $id,
            'status' => 'sent-to-client',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane.doe@example.com',
            'product_lines' => [
                ['name' => 'Keyboard', 'quantity' => 2, 'unit_price' => 150, 'total_unit_price' => 1],
            ],
            'total_price' => 1,
        ])->assertCreated()
            ->assertJsonMissing(['id' => $id])
            ->assertJson(['data' => ['status' => 'draft', 'total_price' => 300]]);
    }

    /** @param array<string, mixed> $overrides */
    #[DataProvider('invalidRequests')]
    public function test_an_invalid_request_is_rejected_and_creates_nothing(array $overrides, string $invalidField): void
    {
        $this->postJson('/api/invoices', [...self::validRequest(), ...$overrides])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($invalidField);

        $this->assertSame(0, InvoiceModel::query()->count());
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidRequests(): iterable
    {
        yield 'missing customer name' => [['customer_name' => null], 'customer_name'];
        yield 'blank customer name' => [['customer_name' => '   '], 'customer_name'];
        yield 'invalid customer email' => [['customer_email' => 'jane doe@example.com'], 'customer_email'];
        yield 'product lines not a list' => [['product_lines' => 'Keyboard'], 'product_lines'];
        yield 'blank product name' => [['product_lines' => [['name' => '', 'quantity' => 1, 'unit_price' => 1]]], 'product_lines.0.name'];
        yield 'zero quantity' => [['product_lines' => [['name' => 'Keyboard', 'quantity' => 0, 'unit_price' => 1]]], 'product_lines.0.quantity'];
        yield 'fractional quantity' => [['product_lines' => [['name' => 'Keyboard', 'quantity' => 1.5, 'unit_price' => 1]]], 'product_lines.0.quantity'];
        yield 'non-numeric quantity' => [['product_lines' => [['name' => 'Keyboard', 'quantity' => 'two', 'unit_price' => 1]]], 'product_lines.0.quantity'];
        yield 'negative unit price' => [['product_lines' => [['name' => 'Keyboard', 'quantity' => 1, 'unit_price' => -150]]], 'product_lines.0.unit_price'];
        yield 'missing unit price' => [['product_lines' => [['name' => 'Keyboard', 'quantity' => 1]]], 'product_lines.0.unit_price'];
        yield 'second line invalid' => [['product_lines' => [
            ['name' => 'Keyboard', 'quantity' => 2, 'unit_price' => 150],
            ['name' => 'Mouse', 'quantity' => 0, 'unit_price' => 10],
        ]], 'product_lines.1.quantity'];
    }

    public function test_all_problems_with_the_values_are_reported_at_once(): void
    {
        $this->postJson('/api/invoices', [
            'customer_name' => '',
            'customer_email' => 'jane.doe',
            'product_lines' => [
                ['name' => 'Keyboard', 'quantity' => 0, 'unit_price' => -1],
            ],
        ])->assertUnprocessable()->assertOnlyJsonValidationErrors([
            'customer_name',
            'customer_email',
            'product_lines.0.quantity',
            'product_lines.0.unit_price',
        ]);
    }

    /** @return array<string, mixed> */
    private static function validRequest(): array
    {
        return [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane.doe@example.com',
            'product_lines' => [
                ['name' => 'Keyboard', 'quantity' => 2, 'unit_price' => 150],
            ],
        ];
    }
}
