<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Repositories;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Models\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Modules\Invoices\Infrastructure\Models\InvoiceModel;
use Modules\Invoices\Infrastructure\Models\ProductLineModel;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    public function get(UuidInterface $id): Invoice
    {
        /** @var InvoiceModel $model */
        $model = InvoiceModel::with('productLines')->findOrFail($id->toString());

        return $this->toDomain($model);
    }

    public function find(UuidInterface $id): ?Invoice
    {
        /** @var InvoiceModel|null $model */
        $model = InvoiceModel::with('productLines')->find($id->toString());

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function save(Invoice $invoice): void
    {
        /** @var InvoiceModel $model */
        $model = InvoiceModel::findOrNew($invoice->id()->toString());

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
    }

    private function toDomain(InvoiceModel $model): Invoice
    {
        /** @var ProductLine[] $productLines */
        $productLines = $model->productLines->map(
            fn (ProductLineModel $lineModel): ProductLine => new ProductLine(
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
