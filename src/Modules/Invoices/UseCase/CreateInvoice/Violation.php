<?php

declare(strict_types=1);

namespace Modules\Invoices\UseCase\CreateInvoice;

final readonly class Violation
{
    public function __construct(
        public string $field,
        public string $message,
        public ?int $productLineIndex = null,
    ) {}
}
