<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Invoices\UseCase\CreateInvoice\CreateInvoiceProductLineRequest;
use Modules\Invoices\UseCase\CreateInvoice\CreateInvoiceRequest;

/**
 * Checks only that the payload can be turned into a CreateInvoiceRequest.
 * Whether its values are acceptable is up to the use case, which reports all
 * of its violations at once; rejecting a blank customer name here would hide
 * them behind this first error.
 */
final class CreateInvoiceFormRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'customer_name' => ['nullable', 'string'],
            'customer_email' => ['nullable', 'string'],
            'product_lines' => ['sometimes', 'list'],
            'product_lines.*' => ['array'],
            'product_lines.*.name' => ['nullable', 'string'],
            'product_lines.*.quantity' => ['required', 'integer'],
            'product_lines.*.unit_price' => ['required', 'integer'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'product_lines.*.name' => 'product name',
            'product_lines.*.quantity' => 'quantity',
            'product_lines.*.unit_price' => 'unit price',
        ];
    }

    public function toUseCaseRequest(): CreateInvoiceRequest
    {
        /** @var array{customer_name?: ?string, customer_email?: ?string, product_lines?: list<array{name?: ?string, quantity: int|string, unit_price: int|string}>} $data */
        $data = $this->validated();

        return new CreateInvoiceRequest(
            customerName: $data['customer_name'] ?? '',
            customerEmail: $data['customer_email'] ?? '',
            productLines: array_map(
                static fn (array $line): CreateInvoiceProductLineRequest => new CreateInvoiceProductLineRequest(
                    name: $line['name'] ?? '',
                    quantity: (int) $line['quantity'],
                    unitPrice: (int) $line['unit_price'],
                ),
                $data['product_lines'] ?? [],
            ),
        );
    }
}
