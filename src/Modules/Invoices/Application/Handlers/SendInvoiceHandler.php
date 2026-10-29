<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Handlers;

use Modules\Invoices\Application\Commands\SendInvoiceCommand;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;

final readonly class SendInvoiceHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $repository,
        private NotificationFacadeInterface $notificationFacade,
    ) {}

    public function handle(SendInvoiceCommand $command): void
    {
        $invoice = $this->repository->get($command->invoiceId);

        $invoice->markAsSending();

        $this->notificationFacade->notify(new NotifyData(
            resourceId: $invoice->id(),
            toEmail: $invoice->customerEmail(),
            subject: 'Invoice',
            message: 'Your invoice is ready.',
        ));

        $this->repository->save($invoice);
    }
}
