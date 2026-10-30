<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\ConcurrentStatusChangeException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Modules\Invoices\Infrastructure\Eloquent\Invoice as InvoiceRecord;
use Modules\Invoices\Infrastructure\Eloquent\ProductLine as ProductLineRecord;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    public function get(UuidInterface $id): Invoice
    {
        /** @var InvoiceRecord|null $model */
        $model = InvoiceRecord::query()->with('productLines')->find($id->toString());

        if ($model === null) {
            throw InvoiceNotFoundException::withId($id);
        }

        return $this->toDomain($model);
    }

    public function find(UuidInterface $id): ?Invoice
    {
        /** @var InvoiceRecord|null $model */
        $model = InvoiceRecord::with('productLines')->find($id->toString());

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function save(Invoice $invoice, ?StatusEnum $expectedStatus = null): void
    {
        $existing = InvoiceRecord::query()->find($invoice->id()->toString());

        if ($existing === null) {
            if ($expectedStatus !== null) {
                throw new LogicException('Cannot persist a new invoice with an expected status.');
            }

            $this->insert($invoice);

            return;
        }

        if ($expectedStatus === null) {
            throw new LogicException('Updating an invoice requires an expected status.');
        }

        $updated = InvoiceRecord::query()
            ->where('id', $invoice->id()->toString())
            ->where('status', $expectedStatus->value)
            ->update([
                'customer_name' => $invoice->customerName(),
                'customer_email' => $invoice->customerEmail(),
                'status' => $invoice->status()->value,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw ConcurrentStatusChangeException::forInvoice($invoice->id());
        }
    }

    private function insert(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $model = new InvoiceRecord;
            $model->id = $invoice->id()->toString();
            $model->customer_name = $invoice->customerName();
            $model->customer_email = $invoice->customerEmail();
            $model->status = $invoice->status()->value;
            $model->save();

            foreach ($invoice->productLines() as $line) {
                $model->productLines()->create([
                    'id' => Uuid::uuid4()->toString(),
                    'name' => $line->name,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unitPrice,
                ]);
            }
        });
    }

    private function toDomain(InvoiceRecord $model): Invoice
    {
        /** @var ProductLine[] $productLines */
        $productLines = $model->productLines->map(
            fn (ProductLineRecord $lineModel): ProductLine => new ProductLine(
                name: $lineModel->name,
                quantity: $lineModel->quantity,
                unitPrice: $lineModel->unit_price,
            ),
        )->all();

        return Invoice::reconstitute(
            id: Uuid::fromString($model->id),
            customerName: $model->customer_name,
            customerEmail: $model->customer_email,
            status: StatusEnum::from($model->status),
            productLines: $productLines,
        );
    }
}
