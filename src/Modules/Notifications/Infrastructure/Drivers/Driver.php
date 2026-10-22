<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Drivers;

interface Driver
{
    public function send(
        string $toEmail,
        string $subject,
        string $message,
        string $reference,
    ): void;
}
