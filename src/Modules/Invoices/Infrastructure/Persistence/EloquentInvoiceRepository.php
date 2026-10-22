<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotStoredException;
use Modules\Invoices\Domain\Repositories\InvoiceRepository;
use Modules\Invoices\Infrastructure\Persistence\Models\Invoice as InvoiceModel;
use Modules\Invoices\Infrastructure\Persistence\Models\InvoiceProductLine as InvoiceProductLineModel;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use ReflectionProperty;
use Throwable;

final readonly class EloquentInvoiceRepository implements InvoiceRepository
{
    public function __construct(
        private ConnectionInterface $connection,
    ) {}

    public function findById(UuidInterface $invoiceId): Invoice
    {
        $model = InvoiceModel::query()
            ->with(['productLines' => static fn ($query) => $query->orderBy('id')])
            ->find($invoiceId->toString());

        if ($model === null) {
            throw new InvoiceNotFoundException($invoiceId);
        }

        return self::toEntity($model);
    }

    /**
     * Product lines have no identity in the domain, so they are replaced
     * rather than updated. Their UUIDv7 IDs are generated in increasing order,
     * which is what lets findInvoiceById() return them in their original order.
     */
    public function save(Invoice $invoice): void
    {
        try {
            $this->connection->transaction(static function () use ($invoice): void {
                $model = InvoiceModel::query()->findOrNew($invoice->id->toString());
                $model->forceFill([
                    'id' => $invoice->id->toString(),
                    'customer_name' => $invoice->customerName,
                    'customer_email' => $invoice->customerEmail,
                    'status' => $invoice->status,
                ])->save();

                $model->productLines()->delete();
                $model->productLines()->createMany(array_map(
                    static fn (ProductLine $line): array => [
                        'name' => $line->name,
                        'quantity' => $line->quantity,
                        'unit_price' => $line->unitPrice,
                    ],
                    $invoice->productLines,
                ));
            });
        } catch (Throwable $exception) {
            throw new InvoiceNotStoredException($invoice->id, $exception);
        }
    }

    private static function toEntity(InvoiceModel $model): Invoice
    {
        $invoice = new Invoice(
            Uuid::fromString($model->id),
            $model->customer_name,
            $model->customer_email,
            ...$model->productLines->map(
                static fn (InvoiceProductLineModel $line): ProductLine => new ProductLine(
                    name: $line->name,
                    quantity: $line->quantity,
                    unitPrice: $line->unit_price,
                ),
            ),
        );

        // Only the entity's own transitions may change its status, so there is
        // no public way to set it; restoring stored state is the exception.
        (new ReflectionProperty(Invoice::class, 'status'))->setValue($invoice, $model->status);

        return $invoice;
    }
}
