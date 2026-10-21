<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Validation;

use Illuminate\Contracts\Validation\Factory;
use Modules\Invoices\UseCase\CreateInvoiceProductLineRequest;
use Modules\Invoices\UseCase\CreateInvoiceRequest;
use Modules\Invoices\UseCase\CreateInvoiceRequestValidatorInterface;
use Modules\Invoices\UseCase\Violation;

final readonly class LaravelCreateInvoiceRequestValidator implements CreateInvoiceRequestValidatorInterface
{
    private const array RULES = [
        'customerName' => ['required', 'string'],
        'customerEmail' => ['required', 'email:filter'],
        'productLines.*.name' => ['required', 'string'],
        'productLines.*.quantity' => ['integer', 'min:1'],
        'productLines.*.unitPrice' => ['integer', 'min:1'],
    ];

    private const array ATTRIBUTES = [
        'customerName' => 'customer name',
        'customerEmail' => 'customer email',
        'productLines.*.name' => 'product name',
        'productLines.*.quantity' => 'quantity',
        'productLines.*.unitPrice' => 'unit price',
    ];

    public function __construct(
        private Factory $validatorFactory,
    ) {}

    public function validate(CreateInvoiceRequest $request): array
    {
        $validator = $this->validatorFactory->make(
            self::toArray($request),
            self::RULES,
            attributes: self::ATTRIBUTES,
        );

        $violations = [];

        foreach ($validator->errors()->messages() as $key => $messages) {
            foreach ($messages as $message) {
                $violations[] = self::toViolation($key, $message);
            }
        }

        return $violations;
    }

    /** @return array<string, mixed> */
    private static function toArray(CreateInvoiceRequest $request): array
    {
        return [
            'customerName' => $request->customerName,
            'customerEmail' => $request->customerEmail,
            'productLines' => array_map(
                static fn (CreateInvoiceProductLineRequest $line): array => [
                    'name' => $line->name,
                    'quantity' => $line->quantity,
                    'unitPrice' => $line->unitPrice,
                ],
                $request->productLines,
            ),
        ];
    }

    /** Turns "productLines.2.quantity" into a violation of "quantity" at index 2. */
    private static function toViolation(string $key, string $message): Violation
    {
        if (preg_match('/^productLines\.(\d+)\.(\w+)$/', $key, $matches) === 1) {
            return new Violation($matches[2], $message, (int) $matches[1]);
        }

        return new Violation($key, $message);
    }
}
