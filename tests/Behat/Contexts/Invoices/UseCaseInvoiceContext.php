<?php

declare(strict_types=1);

namespace Tests\Behat\Contexts\Invoices;

use LogicException;
use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSentException;
use Modules\Invoices\Domain\Exceptions\InvoiceDeliveryCannotBeConfirmedException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\UseCase\ConfirmInvoiceDelivery;
use Modules\Invoices\UseCase\ConfirmInvoiceDeliveryRequest;
use Modules\Invoices\UseCase\CreateInvoice;
use Modules\Invoices\UseCase\CreateInvoiceProductLineRequest;
use Modules\Invoices\UseCase\CreateInvoiceRequest;
use Modules\Invoices\UseCase\Exceptions\InvalidCreateInvoiceRequestException;
use Modules\Invoices\UseCase\Exceptions\InvoiceNotSentException;
use Modules\Invoices\UseCase\SendInvoice;
use Modules\Invoices\UseCase\SendInvoiceRequest;
use Modules\Invoices\UseCase\ViewInvoice;
use Modules\Invoices\UseCase\ViewInvoiceRequest;
use Modules\Invoices\UseCase\Violation;
use Ramsey\Uuid\Uuid;
use Tests\Behat\Support\FailureKind;
use Tests\Behat\Support\InvoiceScenarioState;
use Tests\Behat\Support\LayerFailure;

/**
 * Drives the use cases directly: builds their requests and calls execute().
 * Covers the business rules and the real adapters (validator, repository),
 * without routing, HTTP validation or JSON serialization.
 */
final class UseCaseInvoiceContext extends InvoiceContext
{
    /** Violation fields, as named in the use case, mapped to the labels used in the features. */
    private const array FIELD_LABELS = [
        'customerName' => 'customer name',
        'customerEmail' => 'customer email',
        'name' => 'product name',
        'quantity' => 'quantity',
        'unitPrice' => 'unit price',
    ];

    public function __construct(
        InvoiceScenarioState $state,
        private readonly CreateInvoice $createInvoice,
        private readonly ViewInvoice $viewInvoice,
        private readonly SendInvoice $sendInvoice,
        private readonly ConfirmInvoiceDelivery $confirmInvoiceDelivery,
    ) {
        parent::__construct($state);
    }

    protected function createInvoice(string $customerName, string $customerEmail, array $productLines): array
    {
        $request = new CreateInvoiceRequest(
            customerName: $customerName,
            customerEmail: $customerEmail,
            productLines: array_map(
                static fn (array $row): CreateInvoiceProductLineRequest => new CreateInvoiceProductLineRequest(
                    name: $row['product name'],
                    quantity: self::toInteger($row['quantity']),
                    unitPrice: self::toInteger($row['unit price']),
                ),
                $productLines,
            ),
        );

        try {
            return self::toArray($this->createInvoice->execute($request));
        } catch (InvalidCreateInvoiceRequestException $exception) {
            throw new LayerFailure(
                FailureKind::InvalidRequest,
                $exception->getMessage(),
                array_map(
                    static fn (Violation $violation): array => [
                        self::FIELD_LABELS[$violation->field] ?? $violation->field,
                        $violation->productLineIndex,
                    ],
                    $exception->violations,
                ),
            );
        }
    }

    protected function viewInvoice(string $invoiceId): array
    {
        try {
            return self::toArray($this->viewInvoice->execute(new ViewInvoiceRequest(Uuid::fromString($invoiceId))));
        } catch (InvoiceNotFoundException $exception) {
            throw new LayerFailure(FailureKind::InvoiceNotFound, $exception->getMessage());
        }
    }

    protected function sendInvoice(string $invoiceId): array
    {
        try {
            return self::toArray($this->sendInvoice->execute(new SendInvoiceRequest(Uuid::fromString($invoiceId))));
        } catch (InvoiceNotFoundException $exception) {
            throw new LayerFailure(FailureKind::InvoiceNotFound, $exception->getMessage());
        } catch (InvoiceCannotBeSentException $exception) {
            throw new LayerFailure(FailureKind::InvoiceCannotBeSent, $exception->getMessage());
        } catch (InvoiceNotSentException $exception) {
            throw new LayerFailure(FailureKind::CustomerNotNotified, $exception->getMessage());
        }
    }

    protected function confirmInvoiceDelivery(string $invoiceId): array
    {
        try {
            return self::toArray($this->confirmInvoiceDelivery->execute(
                new ConfirmInvoiceDeliveryRequest(Uuid::fromString($invoiceId)),
            ));
        } catch (InvoiceNotFoundException $exception) {
            throw new LayerFailure(FailureKind::InvoiceNotFound, $exception->getMessage());
        } catch (InvoiceDeliveryCannotBeConfirmedException $exception) {
            throw new LayerFailure(FailureKind::DeliveryCannotBeConfirmed, $exception->getMessage());
        }
    }

    /**
     * The use case only accepts integers, so a value such as "1.5" cannot even
     * be put into a request. Examples like that belong to the API suite only.
     */
    private static function toInteger(string $value): int
    {
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            throw new LogicException(
                sprintf('"%s" is not an integer, so it cannot be passed to a use case. Tag this example @api_only.', $value),
            );
        }

        return (int) $value;
    }

    /** @return array<string, mixed> */
    private static function toArray(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id->toString(),
            'status' => $invoice->status->value,
            'customer_name' => $invoice->customerName,
            'customer_email' => $invoice->customerEmail,
            'product_lines' => array_map(
                static fn (ProductLine $line): array => [
                    'name' => $line->name,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unitPrice,
                    'total_unit_price' => $line->totalUnitPrice(),
                ],
                $invoice->productLines,
            ),
            'total_price' => $invoice->totalPrice(),
        ];
    }
}
