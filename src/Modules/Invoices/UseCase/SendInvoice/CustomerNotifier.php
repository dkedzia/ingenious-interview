<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase\SendInvoice;

use Modules\Invoices\Domain\Entities\Invoice;

interface CustomerNotifier
{
    /**
     * @throws CustomerNotNotifiedException
     */
    public function notifyInvoiceIssued(Invoice $invoice): void;
}
