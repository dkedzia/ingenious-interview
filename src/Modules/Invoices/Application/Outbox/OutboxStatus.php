<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Outbox;

enum OutboxStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Notified = 'notified';
    case Failed = 'failed';
}
