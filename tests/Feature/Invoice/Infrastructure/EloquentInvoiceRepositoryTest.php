<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice\Infrastructure;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Models\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function shouldSaveAndRetrieveInvoiceWithProductLines(): void
    {
        $id = Uuid::uuid4();
        $lines = [
            new ProductLine('Widget', 2, 300),
            new ProductLine('Gadget', 1, 700),
        ];
        $invoice = Invoice::create($id, 'John Doe', 'john@example.com', $lines);

        $this->repository->save($invoice);
        $retrieved = $this->repository->get($id);

        $this->assertTrue($id->equals($retrieved->id()));
        $this->assertSame('John Doe', $retrieved->customerName());
        $this->assertSame('john@example.com', $retrieved->customerEmail());
        $this->assertSame(StatusEnum::Draft, $retrieved->status());
        $this->assertCount(2, $retrieved->productLines());
        $this->assertSame(1300, $retrieved->totalPrice());
    }

    #[Test]
    public function shouldPersistStatusChange(): void
    {
        $id = Uuid::uuid4();
        $lines = [new ProductLine('Widget', 1, 100)];
        $invoice = Invoice::create($id, 'Jane', 'jane@example.com', $lines);
        $invoice->markAsSending();

        $this->repository->save($invoice);
        $retrieved = $this->repository->get($id);

        $this->assertSame(StatusEnum::Sending, $retrieved->status());
    }

    #[Test]
    public function shouldSaveInvoiceWithoutProductLines(): void
    {
        $id = Uuid::uuid4();
        $invoice = Invoice::create($id, 'Jane', 'jane@example.com');

        $this->repository->save($invoice);
        $retrieved = $this->repository->get($id);

        $this->assertEmpty($retrieved->productLines());
        $this->assertSame(0, $retrieved->totalPrice());
    }

    #[Test]
    public function shouldUpdateExistingInvoiceStatus(): void
    {
        $id = Uuid::uuid4();
        $lines = [new ProductLine('Widget', 1, 500)];
        $invoice = Invoice::create($id, 'Jane', 'jane@example.com', $lines);
        $this->repository->save($invoice);

        $persisted = $this->repository->get($id);
        $persisted->markAsSending();
        $this->repository->save($persisted);

        $retrieved = $this->repository->get($id);
        $this->assertSame(StatusEnum::Sending, $retrieved->status());
        $this->assertCount(1, $retrieved->productLines());
    }

    #[Test]
    public function shouldThrowWhenInvoiceNotFound(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->get(Uuid::uuid4());
    }
}
