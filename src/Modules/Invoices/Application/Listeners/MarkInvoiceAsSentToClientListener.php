<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Listeners;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;

final readonly class MarkInvoiceAsSentToClientListener
{
    public function __construct(
        private InvoiceRepositoryInterface $repository,
    ) {}

    public function handle(WebhookDeliveredEvent $event): void
    {
        $invoice = $this->repository->find($event->resourceId);

        if ($invoice === null || $invoice->status() !== StatusEnum::Sending) {
            return;
        }

        $invoice->markAsSentToClient();
        $this->repository->save($invoice);
    }
}
