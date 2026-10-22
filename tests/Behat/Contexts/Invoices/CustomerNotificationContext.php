<?php

declare(strict_types=1);

namespace Tests\Behat\Contexts\Invoices;

use Behat\Behat\Context\Context;
use Behat\Step\Then;
use PHPUnit\Framework\Assert;
use Tests\Behat\Support\FakeNotificationDriver;
use Tests\Behat\Support\InvoiceScenarioState;

final readonly class CustomerNotificationContext implements Context
{
    public function __construct(
        private FakeNotificationDriver $notificationDriver,
        private InvoiceScenarioState $state,
    ) {}

    #[Then('the customer should be notified about the invoice at :email')]
    public function theCustomerShouldBeNotifiedAboutTheInvoiceAt(string $email): void
    {
        $invoiceId = $this->state->currentInvoiceId();

        Assert::assertCount(
            1,
            $this->notificationDriver->sentTo($email, $invoiceId),
            sprintf('Expected one notification about invoice %s sent to %s.', $invoiceId, $email),
        );
    }

    #[Then('the customer should not be notified')]
    public function theCustomerShouldNotBeNotified(): void
    {
        Assert::assertSame([], $this->notificationDriver->sent);
    }

    #[Then('the customer should have been notified once')]
    public function theCustomerShouldHaveBeenNotifiedOnce(): void
    {
        Assert::assertCount(1, $this->notificationDriver->sent);
    }
}
