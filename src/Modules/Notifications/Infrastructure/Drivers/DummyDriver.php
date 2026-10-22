<?php

declare(strict_types=1);

namespace Modules\Notifications\Infrastructure\Drivers;

final class DummyDriver implements Driver
{
    public function send(
        string $toEmail,
        string $subject,
        string $message,
        string $reference,
    ): void {}
}
