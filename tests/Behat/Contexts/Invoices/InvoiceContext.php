<?php

declare(strict_types=1);

namespace Tests\Behat\Contexts\Invoices;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\Step\Then;
use Behat\Step\When;
use PHPUnit\Framework\Assert;
use Ramsey\Uuid\Uuid;
use Tests\Behat\Support\FailureKind;
use Tests\Behat\Support\InvoiceScenarioState;
use Tests\Behat\Support\LayerFailure;

/**
 * The invoice When and Then steps, written once for every layer. Each suite
 * uses exactly one subclass, which implements the actions against its
 * own layer; the steps here never know which layer they are driving. Given
 * steps live in the setup contexts, which every suite shares.
 *
 * Checks on "the invoice" fetch it again through the same layer, so they see
 * what was stored, not just what an action returned.
 */
abstract class InvoiceContext implements Context
{
    public function __construct(
        protected readonly InvoiceScenarioState $state,
    ) {}

    /**
     * @param  list<array<string, string>>  $productLines  table rows as written in the feature
     * @return array<string, mixed>
     *
     * @throws LayerFailure
     */
    abstract protected function createInvoice(string $customerName, string $customerEmail, array $productLines): array;

    /**
     * @return array<string, mixed>
     *
     * @throws LayerFailure
     */
    abstract protected function viewInvoice(string $invoiceId): array;

    /**
     * @return array<string, mixed>
     *
     * @throws LayerFailure
     */
    abstract protected function sendInvoice(string $invoiceId): array;

    /**
     * @return array<string, mixed>|null the invoice, if the layer returns it
     *
     * @throws LayerFailure
     */
    abstract protected function confirmInvoiceDelivery(string $invoiceId): ?array;

    #[When('I create an invoice for customer :customerName with email :customerEmail')]
    public function iCreateAnInvoice(string $customerName, string $customerEmail): void
    {
        $this->create($customerName, $customerEmail, []);
    }

    #[When('I create an invoice for customer :customerName with email :customerEmail and product lines:')]
    public function iCreateAnInvoiceWithProductLines(string $customerName, string $customerEmail, TableNode $productLines): void
    {
        $this->create($customerName, $customerEmail, $productLines->getColumnsHash());
    }

    #[When('I view the invoice')]
    public function iViewTheInvoice(): void
    {
        $invoiceId = $this->state->currentInvoiceId();
        $this->attempt(fn (): array => $this->viewInvoice($invoiceId));
    }

    #[When('I view the invoice :invoiceId')]
    public function iViewTheInvoiceWithId(string $invoiceId): void
    {
        $this->state->refersTo($invoiceId);
        $this->iViewTheInvoice();
    }

    #[When('I view the invoice for customer :customerName')]
    public function iViewTheInvoiceForCustomer(string $customerName): void
    {
        $this->state->refersTo($this->state->invoiceIdOf($customerName));
        $this->iViewTheInvoice();
    }

    #[When('I send the invoice')]
    public function iSendTheInvoice(): void
    {
        $invoiceId = $this->state->currentInvoiceId();
        $this->attempt(fn (): array => $this->sendInvoice($invoiceId));
    }

    #[When('I send the invoice :invoiceId')]
    public function iSendTheInvoiceWithId(string $invoiceId): void
    {
        $this->state->refersTo($invoiceId);
        $this->iSendTheInvoice();
    }

    #[When('the notification provider confirms the delivery of the invoice')]
    public function theNotificationProviderConfirmsTheDeliveryOfTheInvoice(): void
    {
        $invoiceId = $this->state->currentInvoiceId();
        $this->attempt(fn (): ?array => $this->confirmInvoiceDelivery($invoiceId));
    }

    #[When('the notification provider confirms the delivery of invoice :invoiceId')]
    public function theNotificationProviderConfirmsTheDeliveryOfInvoice(string $invoiceId): void
    {
        $this->state->refersTo($invoiceId);
        $this->theNotificationProviderConfirmsTheDeliveryOfTheInvoice();
    }

    #[Then('the invoice should be created')]
    public function theInvoiceShouldBeCreated(): void
    {
        Assert::assertNull(
            $this->state->lastFailure,
            sprintf('The invoice was not created: %s', $this->state->lastFailure?->getMessage()),
        );
        Assert::assertNotNull($this->state->lastSeenInvoice);
    }

    #[Then('the invoice should not be created')]
    public function theInvoiceShouldNotBeCreated(): void
    {
        Assert::assertNotNull($this->state->lastFailure, 'The invoice was created.');
    }

    #[Then('the invoice should have a generated ID')]
    public function theInvoiceShouldHaveAGeneratedId(): void
    {
        Assert::assertTrue(Uuid::isValid($this->state->currentInvoiceId()));
    }

    #[Then('the invoices should have different IDs')]
    public function theInvoicesShouldHaveDifferentIds(): void
    {
        Assert::assertGreaterThan(1, count($this->state->createdInvoiceIds));
        Assert::assertSame($this->state->createdInvoiceIds, array_values(array_unique($this->state->createdInvoiceIds)));
    }

    #[Then("I should see the invoice's ID")]
    public function iShouldSeeTheInvoicesId(): void
    {
        Assert::assertNotNull(
            $this->state->lastSeenInvoice,
            sprintf('The invoice could not be viewed: %s', $this->state->lastFailure?->getMessage()),
        );
        Assert::assertSame($this->state->currentInvoiceId(), $this->state->lastSeenInvoice['id']);
    }

    #[Then('the invoice status should be :status')]
    public function theInvoiceStatusShouldBe(string $status): void
    {
        Assert::assertSame($status, $this->currentInvoice()['status']);
    }

    #[Then('the invoice customer name should be :customerName')]
    public function theInvoiceCustomerNameShouldBe(string $customerName): void
    {
        Assert::assertSame($customerName, $this->currentInvoice()['customer_name']);
    }

    #[Then('the invoice customer email should be :customerEmail')]
    public function theInvoiceCustomerEmailShouldBe(string $customerEmail): void
    {
        Assert::assertSame($customerEmail, $this->currentInvoice()['customer_email']);
    }

    #[Then('the invoice should have no product lines')]
    public function theInvoiceShouldHaveNoProductLines(): void
    {
        Assert::assertSame([], $this->currentInvoice()['product_lines']);
    }

    #[Then('the invoice should have the following product lines:')]
    public function theInvoiceShouldHaveTheFollowingProductLines(TableNode $productLines): void
    {
        $expected = array_map(
            static fn (array $row): array => [
                'name' => $row['product name'],
                'quantity' => (int) $row['quantity'],
                'unit_price' => (int) $row['unit price'],
                'total_unit_price' => (int) $row['total unit price'],
            ],
            $productLines->getColumnsHash(),
        );

        Assert::assertSame($expected, $this->currentInvoice()['product_lines']);
    }

    #[Then('the invoice total price should be :totalPrice')]
    public function theInvoiceTotalPriceShouldBe(string $totalPrice): void
    {
        Assert::assertSame((int) $totalPrice, $this->currentInvoice()['total_price']);
    }

    #[Then('I should be told that :field is invalid')]
    public function iShouldBeToldThatFieldIsInvalid(string $field): void
    {
        $this->assertInvalidField($field, null);
    }

    #[Then('I should be told that :field of product line :line is invalid')]
    public function iShouldBeToldThatFieldOfProductLineIsInvalid(string $field, string $line): void
    {
        $this->assertInvalidField($field, (int) $line - 1);
    }

    #[Then('I should be told that invoice :invoiceId does not exist')]
    public function iShouldBeToldThatInvoiceDoesNotExist(string $invoiceId): void
    {
        $failure = $this->assertFailure(FailureKind::InvoiceNotFound);
        Assert::assertStringContainsString($invoiceId, $failure->getMessage());
    }

    #[Then('I should be told that the invoice cannot be sent because it is not a draft')]
    public function iShouldBeToldThatTheInvoiceIsNotADraft(): void
    {
        $failure = $this->assertFailure(FailureKind::InvoiceCannotBeSent);
        Assert::assertStringContainsString('only draft invoices can be sent', $failure->getMessage());
    }

    #[Then('I should be told that the invoice cannot be sent because it has no product lines')]
    public function iShouldBeToldThatTheInvoiceHasNoProductLines(): void
    {
        $failure = $this->assertFailure(FailureKind::InvoiceCannotBeSent);
        Assert::assertStringContainsString('has no product lines', $failure->getMessage());
    }

    #[Then('I should be told that the invoice could not be sent to the customer')]
    public function iShouldBeToldThatTheInvoiceCouldNotBeSent(): void
    {
        $this->assertFailure(FailureKind::CustomerNotNotified);
    }

    #[Then('I should be told that the delivery cannot be confirmed because the invoice is not being sent')]
    public function iShouldBeToldThatTheDeliveryCannotBeConfirmed(): void
    {
        $failure = $this->assertFailure(FailureKind::DeliveryCannotBeConfirmed);
        Assert::assertStringContainsString('only invoices being sent can be delivered', $failure->getMessage());
    }

    /** @param list<array<string, string>> $productLines */
    private function create(string $customerName, string $customerEmail, array $productLines): void
    {
        $this->attempt(function () use ($customerName, $customerEmail, $productLines): array {
            $invoice = $this->createInvoice($customerName, $customerEmail, $productLines);

            $this->state->createdInvoiceIds[] = $invoice['id'];
            $this->state->rememberInvoice($invoice['id'], $customerName);

            return $invoice;
        });
    }

    /** @param callable(): (array<string, mixed>|null) $action */
    private function attempt(callable $action): void
    {
        $this->state->lastSeenInvoice = null;
        $this->state->lastFailure = null;

        try {
            $this->state->lastSeenInvoice = $action();
        } catch (LayerFailure $failure) {
            $this->state->lastFailure = $failure;
        }
    }

    /** @return array<string, mixed> */
    private function currentInvoice(): array
    {
        $invoiceId = $this->state->currentInvoiceId();

        try {
            return $this->viewInvoice($invoiceId);
        } catch (LayerFailure $failure) {
            Assert::fail(sprintf('Invoice %s could not be viewed: %s', $invoiceId, $failure->getMessage()));
        }
    }

    private function assertFailure(FailureKind $kind): LayerFailure
    {
        $failure = $this->state->lastFailure;

        Assert::assertNotNull($failure, sprintf('Expected the action to fail with %s, but it succeeded.', $kind->name));
        Assert::assertSame(
            $kind,
            $failure->kind,
            sprintf('Expected %s, got %s: %s', $kind->name, $failure->kind->name, $failure->getMessage()),
        );

        return $failure;
    }

    private function assertInvalidField(string $field, ?int $productLineIndex): void
    {
        $failure = $this->assertFailure(FailureKind::InvalidRequest);

        Assert::assertContains(
            [$field, $productLineIndex],
            $failure->invalidFields,
            'Reported invalid fields: '.json_encode($failure->invalidFields),
        );
    }
}
