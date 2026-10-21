<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Entities;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSentException;
use Ramsey\Uuid\UuidInterface;

final class Invoice
{
    public private(set) StatusEnum $status = StatusEnum::Draft;

    /** @var list<ProductLine> */
    public readonly array $productLines;

    /** @throws InvalidInvoiceException */
    public function __construct(
        public readonly UuidInterface $id,
        public readonly string $customerName,
        public readonly string $customerEmail,
        ProductLine ...$productLines,
    ) {
        if (trim($customerName) === '') {
            throw InvalidInvoiceException::blankCustomerName();
        }

        if (filter_var($customerEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidInvoiceException::invalidCustomerEmail($customerEmail);
        }

        $this->productLines = array_values($productLines);
    }

    /**
     * Marks the invoice as being sent. Quantities and unit prices need no check
     * here, because a ProductLine cannot hold anything but positive integers.
     *
     * @throws InvoiceCannotBeSentException
     */
    public function sendToCustomer(): void
    {
        if ($this->status !== StatusEnum::Draft) {
            throw InvoiceCannotBeSentException::notDraft($this->id, $this->status);
        }

        if ($this->productLines === []) {
            throw InvoiceCannotBeSentException::noProductLines($this->id);
        }

        $this->status = StatusEnum::Sending;
    }

    public function totalPrice(): int
    {
        return array_sum(array_map(
            static fn (ProductLine $line): int => $line->totalUnitPrice(),
            $this->productLines,
        ));
    }
}
