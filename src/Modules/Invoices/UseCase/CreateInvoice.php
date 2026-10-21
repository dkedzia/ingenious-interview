<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use Modules\Invoices\Domain\Exceptions\InvalidProductLineException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotStoredException;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Domain\Services\InvoiceIdGeneratorInterface;
use Modules\Invoices\UseCase\Exceptions\InvalidCreateInvoiceRequestException;

final readonly class CreateInvoice
{
    public function __construct(
        private CreateInvoiceRequestValidatorInterface $requestValidator,
        private InvoiceIdGeneratorInterface $idGenerator,
        private InvoiceRepositoryInterface $invoiceRepository,
    ) {}

    /**
     * @throws InvalidCreateInvoiceRequestException
     * @throws InvalidInvoiceException
     * @throws InvalidProductLineException
     * @throws InvoiceNotStoredException
     */
    public function execute(CreateInvoiceRequest $request): Invoice
    {
        $violations = $this->requestValidator->validate($request);

        if ($violations !== []) {
            throw new InvalidCreateInvoiceRequestException($violations);
        }

        $invoice = new Invoice(
            $this->idGenerator->generate(),
            $request->customerName,
            $request->customerEmail,
            ...array_map(
                static fn (CreateInvoiceProductLineRequest $line): ProductLine => new ProductLine(
                    name: $line->name,
                    quantity: $line->quantity,
                    unitPrice: $line->unitPrice,
                ),
                $request->productLines,
            ),
        );

        $this->invoiceRepository->save($invoice);

        return $invoice;
    }
}
