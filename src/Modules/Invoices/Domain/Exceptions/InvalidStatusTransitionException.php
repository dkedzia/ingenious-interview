<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;
use Modules\Invoices\Domain\Enums\StatusEnum;

final class InvalidStatusTransitionException extends DomainException
{
    public static function cannotTransition(StatusEnum $from, StatusEnum $to): self
    {
        return new self("Cannot transition invoice from {$from->value} to {$to->value}.");
    }
}
