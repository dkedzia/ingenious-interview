<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices\Presentation\Http;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class ShowInvoiceTest extends TestCase
{
    private InvoiceRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(InvoiceRepositoryInterface::class);
    }

    public function test_it_returns_the_invoice_with_its_product_lines_and_totals(): void
    {
        $invoice = new Invoice(
            Uuid::uuid7(),
            'Jane Doe',
            'jane.doe@example.com',
            new ProductLine('Keyboard', 2, 150),
            new ProductLine('Mouse', 3, 10),
        );
        $this->repository->save($invoice);

        $this->getJson(route('invoices.show', ['id' => $invoice->id->toString()]))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $invoice->id->toString(),
                    'status' => 'draft',
                    'customer_name' => 'Jane Doe',
                    'customer_email' => 'jane.doe@example.com',
                    'product_lines' => [
                        ['name' => 'Keyboard', 'quantity' => 2, 'unit_price' => 150, 'total_unit_price' => 300],
                        ['name' => 'Mouse', 'quantity' => 3, 'unit_price' => 10, 'total_unit_price' => 30],
                    ],
                    'total_price' => 330,
                ],
            ]);
    }

    public function test_it_returns_the_current_status(): void
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com', new ProductLine('Keyboard', 2, 150));
        $invoice->sendToCustomer();
        $this->repository->save($invoice);

        $this->getJson(route('invoices.show', ['id' => $invoice->id->toString()]))
            ->assertOk()
            ->assertJsonPath('data.status', 'sending');
    }

    public function test_an_invoice_without_product_lines_has_a_total_price_of_zero(): void
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com');
        $this->repository->save($invoice);

        $this->getJson(route('invoices.show', ['id' => $invoice->id->toString()]))
            ->assertOk()
            ->assertJsonPath('data.product_lines', [])
            ->assertJsonPath('data.total_price', 0);
    }

    public function test_an_unknown_invoice_is_not_found(): void
    {
        $this->getJson(route('invoices.show', ['id' => Uuid::uuid7()->toString()]))
            ->assertNotFound();
    }

    public function test_an_id_that_is_not_a_uuid_is_not_found(): void
    {
        $this->getJson('/api/invoices/not-a-uuid')
            ->assertNotFound();
    }
}
