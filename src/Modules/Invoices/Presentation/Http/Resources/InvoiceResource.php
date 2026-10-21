<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;

/** @property Invoice $resource */
final class InvoiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id->toString(),
            'status' => $this->resource->status->value,
            'customer_name' => $this->resource->customerName,
            'customer_email' => $this->resource->customerEmail,
            'product_lines' => array_map(
                static fn (ProductLine $line): array => [
                    'name' => $line->name,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unitPrice,
                    'total_unit_price' => $line->totalUnitPrice(),
                ],
                $this->resource->productLines,
            ),
            'total_price' => $this->resource->totalPrice(),
        ];
    }
}
