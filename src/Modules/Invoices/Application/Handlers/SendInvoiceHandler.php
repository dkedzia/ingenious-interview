<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Handlers;

use Illuminate\Support\Facades\DB;
use Modules\Invoices\Application\Commands\SendInvoiceCommand;
use Modules\Invoices\Application\Outbox\InvoiceNotificationOutboxInterface;
use Modules\Invoices\Application\Services\InvoiceNotificationDeliveryInterface;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\ConcurrentStatusChangeException;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;

final readonly class SendInvoiceHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $repository,
        private InvoiceNotificationOutboxInterface $outbox,
        private InvoiceNotificationDeliveryInterface $delivery,
    ) {}

    public function handle(SendInvoiceCommand $command): void
    {
        while (true) {
            $invoice = $this->repository->get($command->invoiceId);

            if ($invoice->status() === StatusEnum::SentToClient) {
                throw InvalidStatusTransitionException::cannotTransition(
                    StatusEnum::SentToClient,
                    StatusEnum::Sending,
                );
            }

            if ($invoice->status() === StatusEnum::Sending) {
                $this->deliverIfNeeded($invoice);

                return;
            }

            $invoice->markAsSending();

            try {
                DB::transaction(function () use ($invoice): void {
                    $this->repository->save($invoice, StatusEnum::Draft);
                    $this->outbox->enqueuePending($invoice->id());
                });
            } catch (ConcurrentStatusChangeException) {
                continue;
            }

            $this->delivery->deliver($invoice->id());

            return;
        }
    }

    private function deliverIfNeeded(Invoice $invoice): void
    {
        if ($this->outbox->isNotified($invoice->id())) {
            return;
        }

        $this->delivery->deliver($invoice->id());
    }
}
