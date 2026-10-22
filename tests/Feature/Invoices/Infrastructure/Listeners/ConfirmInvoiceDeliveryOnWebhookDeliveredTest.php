<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices\Infrastructure\Listeners;

use Illuminate\Testing\TestResponse;
use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Tests\TestCase;

/** Goes through the Notifications webhook, to prove the listener is registered. */
final class ConfirmInvoiceDeliveryOnWebhookDeliveredTest extends TestCase
{
    private InvoiceRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(InvoiceRepositoryInterface::class);
    }

    public function test_the_delivery_webhook_marks_an_invoice_being_sent_as_sent_to_client(): void
    {
        $invoice = $this->storedInvoice(send: true);

        $this->deliveryWebhook($invoice->id)->assertNoContent();

        $this->assertSame(StatusEnum::SentToClient, $this->repository->findById($invoice->id)->status);
    }

    public function test_a_repeated_webhook_is_ignored(): void
    {
        $invoice = $this->storedInvoice(send: true);
        $this->deliveryWebhook($invoice->id);

        $this->deliveryWebhook($invoice->id)->assertNoContent();

        $this->assertSame(StatusEnum::SentToClient, $this->repository->findById($invoice->id)->status);
    }

    public function test_a_webhook_for_a_draft_invoice_fails_so_that_the_provider_retries_it(): void
    {
        $invoice = $this->storedInvoice(send: false);

        $this->deliveryWebhook($invoice->id)->assertInternalServerError();

        $this->assertSame(StatusEnum::Draft, $this->repository->findById($invoice->id)->status);
    }

    /** The webhook can arrive after SendInvoice has notified the customer, but before it has saved the invoice. */
    public function test_a_retried_webhook_confirms_the_delivery_once_the_invoice_is_being_sent(): void
    {
        $invoice = $this->storedInvoice(send: false);
        $this->deliveryWebhook($invoice->id)->assertInternalServerError();

        $invoice->sendToCustomer();
        $this->repository->save($invoice);

        $this->deliveryWebhook($invoice->id)->assertNoContent();
        $this->assertSame(StatusEnum::SentToClient, $this->repository->findById($invoice->id)->status);
    }

    public function test_a_webhook_for_a_resource_that_is_not_an_invoice_is_ignored(): void
    {
        $this->deliveryWebhook(Uuid::uuid7())->assertNoContent();
    }

    private function storedInvoice(bool $send): Invoice
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com', new ProductLine('Keyboard', 2, 150));

        if ($send) {
            $invoice->sendToCustomer();
        }

        $this->repository->save($invoice);

        return $invoice;
    }

    private function deliveryWebhook(UuidInterface $reference): TestResponse
    {
        return $this->getJson(route('notification.hook', [
            'action' => 'delivered',
            'reference' => $reference->toString(),
        ]));
    }
}
