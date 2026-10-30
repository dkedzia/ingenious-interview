<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Invoices\Domain\Exceptions\InvalidCustomerException;
use Modules\Invoices\Domain\Exceptions\InvalidProductLineException;
use Modules\Invoices\Domain\Exceptions\InvoiceTotalOverflowException;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ValueObjects\ProductLine;
use Ramsey\Uuid\Uuid;

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
            'product_lines.*.quantity' => ['required', 'bail', $this->jsonInteger(), 'integer', 'min:1'],
            'product_lines.*.unit_price' => ['required', 'bail', $this->jsonInteger(), 'integer', 'min:1'],
        ];
    }

    private function jsonInteger(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_int($value)) {
                $fail("The {$attribute} must be an integer.");
            }
        };
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                try {
                    $lines = array_map(
                        static fn (array $line): ProductLine => new ProductLine(
                            name: $line['name'],
                            quantity: (int) $line['quantity'],
                            unitPrice: (int) $line['unit_price'],
                        ),
                        $this->input('product_lines', []),
                    );

                    Invoice::create(
                        id: Uuid::uuid4(),
                        customerName: (string) $this->input('customer_name'),
                        customerEmail: (string) $this->input('customer_email'),
                        productLines: $lines,
                    );
                } catch (InvalidCustomerException|InvalidProductLineException|InvoiceTotalOverflowException $e) {
                    $validator->errors()->add('invoice', $e->getMessage());
                }
            },
        ];
    }
}
