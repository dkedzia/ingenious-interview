<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateInvoiceRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'product_lines' => ['sometimes', 'array'],
            'product_lines.*.name' => ['required', 'string', 'max:255'],
            'product_lines.*.quantity' => ['required', 'integer', 'min:1'],
            'product_lines.*.unit_price' => ['required', 'integer', 'min:1'],
        ];
    }
}
