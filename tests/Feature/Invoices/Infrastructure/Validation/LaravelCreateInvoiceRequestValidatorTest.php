<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices\Infrastructure\Validation;

use Modules\Invoices\Infrastructure\Validation\LaravelCreateInvoiceRequestValidator;
use Modules\Invoices\UseCase\CreateInvoiceProductLineRequest;
use Modules\Invoices\UseCase\CreateInvoiceRequest;
use Modules\Invoices\UseCase\Violation;
use Tests\TestCase;

final class LaravelCreateInvoiceRequestValidatorTest extends TestCase
{
    private LaravelCreateInvoiceRequestValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = $this->app->make(LaravelCreateInvoiceRequestValidator::class);
    }

    public function test_a_valid_request_has_no_violations(): void
    {
        $this->assertSame([], $this->validator->validate(new CreateInvoiceRequest(
            customerName: 'Jane Doe',
            customerEmail: 'jane.doe@example.com',
            productLines: [new CreateInvoiceProductLineRequest('Keyboard', 1, 1)],
        )));
    }

    public function test_every_violation_is_reported_with_its_field_and_product_line(): void
    {
        $violations = $this->validator->validate(new CreateInvoiceRequest(
            customerName: ' ',
            customerEmail: 'jane.doe@',
            productLines: [
                new CreateInvoiceProductLineRequest('Keyboard', 2, 150),
                new CreateInvoiceProductLineRequest('', 0, -1),
            ],
        ));

        $this->assertSame(
            [
                ['customerName', null],
                ['customerEmail', null],
                ['name', 1],
                ['quantity', 1],
                ['unitPrice', 1],
            ],
            array_map(
                static fn (Violation $violation): array => [$violation->field, $violation->productLineIndex],
                $violations,
            ),
        );
        $this->assertSame('The customer name field is required.', $violations[0]->message);
        $this->assertSame('The unit price field must be at least 1.', $violations[4]->message);
    }
}
