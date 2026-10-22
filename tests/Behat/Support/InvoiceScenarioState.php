<?php

declare(strict_types=1);

namespace Tests\Behat\Support;

use PHPUnit\Framework\Assert;

/**
 * What the steps of one scenario need to share. It is a singleton in the
 * container Behat builds for every scenario, so it never outlives one.
 *
 * Invoices are represented as the API shows them, so both layers compare the
 * same shape: id, status, customer_name, customer_email, product_lines (each
 * with name, quantity, unit_price, total_unit_price) and total_price.
 */
final class InvoiceScenarioState
{
    /** @var array<string, mixed>|null the invoice the last action returned */
    public ?array $lastSeenInvoice = null;

    public ?LayerFailure $lastFailure = null;

    /** @var list<string> */
    public array $createdInvoiceIds = [];

    /** The invoice that "the invoice" in a step refers to. */
    private ?string $currentInvoiceId = null;

    /** @var array<string, string> invoice IDs keyed by customer name */
    private array $invoiceIdsByCustomer = [];

    public function rememberInvoice(string $invoiceId, string $customerName): void
    {
        $this->currentInvoiceId = $invoiceId;
        $this->invoiceIdsByCustomer[$customerName] = $invoiceId;
    }

    public function refersTo(string $invoiceId): void
    {
        $this->currentInvoiceId = $invoiceId;
    }

    public function currentInvoiceId(): string
    {
        Assert::assertNotNull($this->currentInvoiceId, 'No invoice has been mentioned in this scenario yet.');

        return $this->currentInvoiceId;
    }

    public function invoiceIdOf(string $customerName): string
    {
        Assert::assertArrayHasKey(
            $customerName,
            $this->invoiceIdsByCustomer,
            sprintf('No invoice for customer "%s" has been mentioned.', $customerName),
        );

        return $this->invoiceIdsByCustomer[$customerName];
    }
}
