<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\UseCase\CreateInvoice;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use Modules\Invoices\Domain\Exceptions\InvalidProductLineException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotStoredException;
use Modules\Invoices\Domain\Repositories\InvoiceRepository;
use Modules\Invoices\Domain\Services\InvoiceIdGenerator;
use Modules\Invoices\UseCase\CreateInvoice\CreateInvoice;
use Modules\Invoices\UseCase\CreateInvoice\CreateInvoiceProductLineRequest;
use Modules\Invoices\UseCase\CreateInvoice\CreateInvoiceRequest;
use Modules\Invoices\UseCase\CreateInvoice\CreateInvoiceRequestValidator;
use Modules\Invoices\UseCase\CreateInvoice\InvalidCreateInvoiceRequestException;
use Modules\Invoices\UseCase\CreateInvoice\Violation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class CreateInvoiceTest extends TestCase
{
    private CreateInvoiceRequestValidator&Stub $requestValidator;

    private InvoiceIdGenerator&MockObject $idGenerator;

    private InvoiceRepository&MockObject $invoiceRepository;

    private UuidInterface $generatedId;

    private CreateInvoice $createInvoice;

    protected function setUp(): void
    {
        $this->generatedId = Uuid::uuid7();

        $this->requestValidator = $this->createStub(CreateInvoiceRequestValidator::class);
        $this->requestValidator->method('validate')->willReturn([]);

        $this->idGenerator = $this->createMock(InvoiceIdGenerator::class);
        $this->idGenerator->method('generate')->willReturn($this->generatedId);

        $this->invoiceRepository = $this->createMock(InvoiceRepository::class);

        $this->createInvoice = new CreateInvoice(
            $this->requestValidator,
            $this->idGenerator,
            $this->invoiceRepository,
        );
    }

    public function test_it_saves_a_draft_invoice_built_from_the_request(): void
    {
        $saved = $this->captureSavedInvoice();

        $created = $this->createInvoice->execute(new CreateInvoiceRequest(
            customerName: 'Jane Doe',
            customerEmail: 'jane.doe@example.com',
            productLines: [
                new CreateInvoiceProductLineRequest('Keyboard', 2, 150),
                new CreateInvoiceProductLineRequest('Mouse', 3, 10),
            ],
        ));

        $invoice = $saved();
        $this->assertSame($invoice, $created);
        $this->assertTrue($invoice->id->equals($this->generatedId));
        $this->assertSame(StatusEnum::Draft, $invoice->status);
        $this->assertSame('Jane Doe', $invoice->customerName);
        $this->assertSame('jane.doe@example.com', $invoice->customerEmail);
        $this->assertEquals(
            [new ProductLine('Keyboard', 2, 150), new ProductLine('Mouse', 3, 10)],
            $invoice->productLines,
        );
        $this->assertSame(330, $invoice->totalPrice());
    }

    public function test_it_saves_an_invoice_without_product_lines(): void
    {
        $saved = $this->captureSavedInvoice();

        $this->createInvoice->execute(new CreateInvoiceRequest('Jane Doe', 'jane.doe@example.com'));

        $this->assertSame([], $saved()->productLines);
    }

    public function test_it_validates_the_given_request(): void
    {
        $request = new CreateInvoiceRequest('Jane Doe', 'jane.doe@example.com');

        $requestValidator = $this->createMock(CreateInvoiceRequestValidator::class);
        $requestValidator->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($request))
            ->willReturn([]);

        new CreateInvoice($requestValidator, $this->idGenerator, $this->invoiceRepository)->execute($request);
    }

    public function test_an_invalid_request_is_rejected_with_all_violations_and_nothing_is_saved(): void
    {
        $violations = [
            new Violation('customerName', 'The customer name must not be blank.'),
            new Violation('quantity', 'The quantity must be a positive integer.', 1),
        ];

        $requestValidator = $this->createStub(CreateInvoiceRequestValidator::class);
        $requestValidator->method('validate')->willReturn($violations);

        $this->idGenerator->expects($this->never())->method('generate');
        $this->invoiceRepository->expects($this->never())->method('save');

        try {
            new CreateInvoice($requestValidator, $this->idGenerator, $this->invoiceRepository)
                ->execute(new CreateInvoiceRequest('', 'jane.doe@example.com'));

            $this->fail('Expected the request to be rejected.');
        } catch (InvalidCreateInvoiceRequestException $exception) {
            $this->assertSame($violations, $exception->violations);
        }
    }

    public function test_invalid_customer_details_missed_by_the_validator_are_still_rejected(): void
    {
        $this->invoiceRepository->expects($this->never())->method('save');

        $this->expectException(InvalidInvoiceException::class);

        $this->createInvoice->execute(new CreateInvoiceRequest('Jane Doe', 'not-an-email'));
    }

    public function test_an_invalid_product_line_missed_by_the_validator_is_still_rejected(): void
    {
        $this->invoiceRepository->expects($this->never())->method('save');

        $this->expectException(InvalidProductLineException::class);

        $this->createInvoice->execute(new CreateInvoiceRequest(
            customerName: 'Jane Doe',
            customerEmail: 'jane.doe@example.com',
            productLines: [new CreateInvoiceProductLineRequest('Keyboard', 0, 150)],
        ));
    }

    public function test_a_storage_failure_is_reported(): void
    {
        $failure = new InvoiceNotStoredException($this->generatedId);
        $this->invoiceRepository->method('save')->willThrowException($failure);

        $this->expectExceptionObject($failure);

        $this->createInvoice->execute(new CreateInvoiceRequest('Jane Doe', 'jane.doe@example.com'));
    }

    /**
     * Expects exactly one save and returns a getter for the invoice it received.
     *
     * @return callable(): Invoice
     */
    private function captureSavedInvoice(): callable
    {
        $saved = null;

        $this->invoiceRepository->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (Invoice $invoice) use (&$saved): void {
                $saved = $invoice;
            });

        return static function () use (&$saved): Invoice {
            self::assertInstanceOf(Invoice::class, $saved);

            return $saved;
        };
    }
}
