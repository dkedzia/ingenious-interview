<?php

declare(strict_types=1);

namespace Tests\Behat\Contexts\Setup;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Exceptions\InvoicesException;
use Modules\Invoices\Infrastructure\Persistence\Models\Invoice as InvoiceModel;
use Modules\Invoices\UseCase\ConfirmInvoiceDelivery\ConfirmInvoiceDelivery;
use Modules\Invoices\UseCase\ConfirmInvoiceDelivery\ConfirmInvoiceDeliveryRequest;
use Modules\Invoices\UseCase\SendInvoice\SendInvoice;
use Modules\Invoices\UseCase\SendInvoice\SendInvoiceRequest;
use Ramsey\Uuid\Uuid;
use Tests\Behat\Support\InvoiceScenarioState;

/**
 * Arranges invoices for a scenario. Shared by every invoice suite, because
 * arranging is not what a suite verifies: only its When and Then steps go
 * through the layer under test.
 */
final readonly class InvoiceSetupContext implements Context
{
    public function __construct(
        private SendInvoice $sendInvoice,
        private ConfirmInvoiceDelivery $confirmInvoiceDelivery,
        private InvoiceScenarioState $state,
    ) {}

    #[Given('a draft invoice for customer :customerName with email :customerEmail and product lines:')]
    public function aDraftInvoiceWithProductLines(string $customerName, string $customerEmail, TableNode $productLines): void
    {
        $this->createInvoice(StatusEnum::Draft, $customerName, $customerEmail, $productLines->getColumnsHash());
    }

    #[Given('a draft invoice for customer :customerName with email :customerEmail without product lines')]
    public function aDraftInvoiceWithoutProductLines(string $customerName, string $customerEmail): void
    {
        $this->createInvoice(StatusEnum::Draft, $customerName, $customerEmail, []);
    }

    #[Given('an invoice in status :status for customer :customerName with email :customerEmail and product lines:')]
    public function anInvoiceInStatus(string $status, string $customerName, string $customerEmail, TableNode $productLines): void
    {
        $this->createInvoice(StatusEnum::from($status), $customerName, $customerEmail, $productLines->getColumnsHash());
    }

    #[Given('I have sent the invoice')]
    public function iHaveSentTheInvoice(): void
    {
        $this->sendInvoice->execute(new SendInvoiceRequest(Uuid::fromString($this->state->currentInvoiceId())));
    }

    #[Given('I have tried to send the invoice')]
    public function iHaveTriedToSendTheInvoice(): void
    {
        try {
            $this->iHaveSentTheInvoice();
        } catch (InvoicesException) {
            // Whether it worked does not matter, only that it was attempted.
        }
    }

    #[Given('the notification provider has confirmed the delivery of the invoice')]
    public function theNotificationProviderHasConfirmedTheDeliveryOfTheInvoice(): void
    {
        $this->confirmInvoiceDelivery->execute(
            new ConfirmInvoiceDeliveryRequest(Uuid::fromString($this->state->currentInvoiceId())),
        );
    }

    /**
     * @param  list<array<string, string>>  $productLines
     */
    private function createInvoice(StatusEnum $status, string $customerName, string $customerEmail, array $productLines): void
    {
        $invoice = new InvoiceModel;
        $invoice->forceFill([
            'id' => Uuid::uuid7()->toString(),
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'status' => $status,
        ])->save();

        $invoice->productLines()->createMany(array_map(
            static fn (array $row): array => [
                'name' => $row['product name'],
                'quantity' => (int) $row['quantity'],
                'unit_price' => (int) $row['unit price'],
            ],
            $productLines,
        ));

        $this->state->rememberInvoice($invoice->id, $customerName);
    }
}
