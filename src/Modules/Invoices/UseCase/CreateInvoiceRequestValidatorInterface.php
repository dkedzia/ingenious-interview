<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase;

interface CreateInvoiceRequestValidatorInterface
{
    public function validate(CreateInvoiceRequest $request): array;
}
