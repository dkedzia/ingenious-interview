<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Handlers;

use Modules\Invoices\Application\Commands\SendInvoiceCommand;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;
use RuntimeException;
use Throwable;

final readonly class SendInvoiceHandler
{
    public function __construct(
        private InvoiceRepositoryInterface $repository,
        private NotificationFacadeInterface $notificationFacade,
    ) {}

    public function handle(SendInvoiceCommand $command): void
    {
        $invoice = $this->repository->transaction(function () use ($command): Invoice {
            $invoice = $this->repository->get($command->invoiceId, forUpdate: true);
            $invoice->markAsSending();
            $this->repository->save($invoice);

            return $invoice;
        });

        try {
            $this->notificationFacade->notify(new NotifyData(
                resourceId: $invoice->id(),
                toEmail: $invoice->customerEmail(),
                subject: 'Invoice',
                message: 'Your invoice is ready.',
            ));
        } catch (Throwable $notificationFailure) {
            try {
                $this->repository->transaction(function () use ($invoice): void {
                    $persisted = $this->repository->get($invoice->id(), forUpdate: true);

                    if ($persisted->status() !== StatusEnum::Sending) {
                        return;
                    }

                    $persisted->revertSending();
                    $this->repository->save($persisted);
                });
            } catch (Throwable $compensationFailure) {
                throw new RuntimeException(
                    $notificationFailure->getMessage(),
                    0,
                    $compensationFailure,
                );
            }

            throw $notificationFailure;
        }
    }
}
