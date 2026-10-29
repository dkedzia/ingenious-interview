<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Handlers;

use Modules\Invoices\Application\Commands\CreateInvoiceCommand;
use Modules\Invoices\Application\DTOs\ProductLineData;
use Modules\Invoices\Domain\Models\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Ramsey\Uuid\Uuid;

final readonly class CreateInvoiceHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $repository,
    ) {}

    public function handle(CreateInvoiceCommand $command): Invoice
    {
        $productLines = array_map(
            fn (ProductLineData $data): ProductLine => new ProductLine(
                name: $data->name,
                quantity: $data->quantity,
                unitPrice: $data->unitPrice,
            ),
            $command->productLines,
        );

        $invoice = Invoice::create(
            id: Uuid::uuid4(),
            customerName: $command->customerName,
            customerEmail: $command->customerEmail,
            productLines: $productLines,
        );

        $this->repository->save($invoice);

        return $invoice;
    }
}
