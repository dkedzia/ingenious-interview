<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase\CreateInvoice;

interface CreateInvoiceRequestValidator
{
    public function validate(CreateInvoiceRequest $request): array;
}
