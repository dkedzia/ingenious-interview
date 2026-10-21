<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices\Infrastructure\Persistence;

use Illuminate\Support\Facades\Schema;
use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotStoredException;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Infrastructure\Persistence\Models\Invoice as InvoiceModel;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class EloquentInvoiceRepositoryTest extends TestCase
{
    private InvoiceRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(InvoiceRepositoryInterface::class);
    }

    public function test_a_saved_invoice_can_be_found_by_its_id(): void
    {
        $invoice = new Invoice(
            Uuid::uuid7(),
            'Jane Doe',
            'jane.doe@example.com',
            new ProductLine('Keyboard', 2, 150),
            new ProductLine('Mouse', 3, 10),
            new ProductLine('Cable', 1, 1),
        );

        $this->repository->save($invoice);
        $found = $this->repository->findById($invoice->id);

        $this->assertNotSame($invoice, $found);
        $this->assertTrue($found->id->equals($invoice->id));
        $this->assertSame('Jane Doe', $found->customerName);
        $this->assertSame('jane.doe@example.com', $found->customerEmail);
        $this->assertSame(StatusEnum::Draft, $found->status);
        $this->assertEquals($invoice->productLines, $found->productLines);
        $this->assertSame(331, $found->totalPrice());
    }

    public function test_an_invoice_without_product_lines_can_be_saved_and_found(): void
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com');

        $this->repository->save($invoice);

        $this->assertSame([], $this->repository->findById($invoice->id)->productLines);
    }

    public function test_saving_an_existing_invoice_updates_it_instead_of_adding_another(): void
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com', new ProductLine('Keyboard', 2, 150));
        $this->repository->save($invoice);

        $invoice->sendToCustomer();
        $this->repository->save($invoice);

        $found = $this->repository->findById($invoice->id);
        $this->assertSame(StatusEnum::Sending, $found->status);
        $this->assertEquals($invoice->productLines, $found->productLines);
        $this->assertSame(1, InvoiceModel::query()->count());
    }

    public function test_finding_an_unknown_invoice_fails(): void
    {
        $invoiceId = Uuid::uuid7();

        try {
            $this->repository->findById($invoiceId);

            $this->fail('Expected the invoice not to be found.');
        } catch (InvoiceNotFoundException $exception) {
            $this->assertSame($invoiceId, $exception->invoiceId);
        }
    }

    public function test_a_failed_save_stores_nothing(): void
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com', new ProductLine('Keyboard', 2, 150));
        Schema::drop('invoice_product_lines');

        try {
            $this->repository->save($invoice);

            $this->fail('Expected the invoice not to be stored.');
        } catch (InvoiceNotStoredException $exception) {
            $this->assertSame($invoice->id, $exception->invoiceId);
            $this->assertNotNull($exception->getPrevious());
        }

        $this->assertSame(0, InvoiceModel::query()->count());
    }
}
