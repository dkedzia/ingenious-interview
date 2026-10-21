<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices\Presentation\Http;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Tests\TestCase;

final class SendInvoiceTest extends TestCase
{
    private InvoiceRepositoryInterface $repository;

    private NotificationFacadeInterface&MockObject $notificationFacade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(InvoiceRepositoryInterface::class);

        $this->notificationFacade = $this->createMock(NotificationFacadeInterface::class);
        $this->app->instance(NotificationFacadeInterface::class, $this->notificationFacade);
    }

    public function test_it_sends_a_draft_invoice_and_returns_it_as_sending(): void
    {
        $invoice = $this->storedInvoice(new ProductLine('Keyboard', 2, 150));

        $this->notificationFacade->expects($this->once())
            ->method('notify')
            ->with($this->callback(static fn (NotifyData $data): bool => $data->resourceId->equals($invoice->id)
                && $data->toEmail === 'jane.doe@example.com'));

        $this->postJson(route('invoices.send', ['id' => $invoice->id->toString()]))
            ->assertOk()
            ->assertJsonPath('data.id', $invoice->id->toString())
            ->assertJsonPath('data.status', 'sending')
            ->assertJsonPath('data.total_price', 300);

        $this->assertSame(StatusEnum::Sending, $this->repository->findById($invoice->id)->status);
    }

    public function test_an_invoice_without_product_lines_is_a_conflict(): void
    {
        $invoice = $this->storedInvoice();

        $this->notificationFacade->expects($this->never())->method('notify');

        $this->postJson(route('invoices.send', ['id' => $invoice->id->toString()]))
            ->assertConflict()
            ->assertJsonPath('message', "Invoice {$invoice->id->toString()} cannot be sent because it has no product lines.");

        $this->assertSame(StatusEnum::Draft, $this->repository->findById($invoice->id)->status);
    }

    public function test_an_invoice_that_is_not_a_draft_is_a_conflict(): void
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com', new ProductLine('Keyboard', 2, 150));
        $invoice->sendToCustomer();
        $this->repository->save($invoice);

        $this->notificationFacade->expects($this->never())->method('notify');

        $this->postJson(route('invoices.send', ['id' => $invoice->id->toString()]))
            ->assertConflict();

        $this->assertSame(StatusEnum::Sending, $this->repository->findById($invoice->id)->status);
    }

    public function test_a_failed_notification_is_reported_as_unavailable_and_the_invoice_stays_a_draft(): void
    {
        $invoice = $this->storedInvoice(new ProductLine('Keyboard', 2, 150));

        $this->notificationFacade->method('notify')->willThrowException(new RuntimeException('Provider unavailable.'));

        $this->postJson(route('invoices.send', ['id' => $invoice->id->toString()]))
            ->assertServiceUnavailable();

        $this->assertSame(StatusEnum::Draft, $this->repository->findById($invoice->id)->status);
    }

    public function test_an_unknown_invoice_is_not_found(): void
    {
        $this->notificationFacade->expects($this->never())->method('notify');

        $this->postJson(route('invoices.send', ['id' => Uuid::uuid7()->toString()]))
            ->assertNotFound();
    }

    public function test_an_id_that_is_not_a_uuid_is_not_found(): void
    {
        $this->postJson('/api/invoices/not-a-uuid/send')
            ->assertNotFound();
    }

    private function storedInvoice(ProductLine ...$productLines): Invoice
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com', ...$productLines);
        $this->repository->save($invoice);

        return $invoice;
    }
}
