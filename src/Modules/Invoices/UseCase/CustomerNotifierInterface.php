<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\UseCase\Exceptions\CustomerNotNotifiedException;

interface CustomerNotifierInterface
{
    /**
     * @throws CustomerNotNotifiedException
     */
    public function notifyInvoiceIssued(Invoice $invoice): void;
}
