<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Invoices\Domain\Enums\StatusEnum;
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
    public function get(UuidInterface $id, bool $forUpdate = false): Invoice
    {
        $query = InvoiceRecord::query()->where('id', $id->toString());

        if ($forUpdate) {
            $query->lockForUpdate();
        }

        /** @var InvoiceRecord|null $model */
        $model = $query->with('productLines')->first();

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

    public function save(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            /** @var InvoiceRecord $model */
            $model = InvoiceRecord::findOrNew($invoice->id()->toString());

            $isNew = !$model->exists;

            $model->id = $invoice->id()->toString();
            $model->customer_name = $invoice->customerName();
            $model->customer_email = $invoice->customerEmail();
            $model->status = $invoice->status()->value;
            $model->save();

            if ($isNew) {
                foreach ($invoice->productLines() as $line) {
                    $model->productLines()->create([
                        'id' => Uuid::uuid4()->toString(),
                        'name' => $line->name,
                        'quantity' => $line->quantity,
                        'unit_price' => $line->unitPrice,
                    ]);
                }
            }
        });
    }

    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
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
