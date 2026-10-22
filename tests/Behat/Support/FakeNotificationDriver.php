<?php

declare(strict_types=1);

namespace Tests\Behat\Support;

use Modules\Notifications\Infrastructure\Drivers\DriverInterface;
use RuntimeException;

/**
 * Replaces the notification provider, so scenarios can see what was sent and
 * make sending fail. Only successfully sent notifications are recorded.
 */
final class FakeNotificationDriver implements DriverInterface
{
    public bool $available = true;

    /** @var list<array{toEmail: string, subject: string, message: string, reference: string}> */
    public private(set) array $sent = [];

    public function send(
        string $toEmail,
        string $subject,
        string $message,
        string $reference,
    ): void {
        if (! $this->available) {
            throw new RuntimeException('The notification provider is unavailable.');
        }

        $this->sent[] = [
            'toEmail' => $toEmail,
            'subject' => $subject,
            'message' => $message,
            'reference' => $reference,
        ];
    }

    /**
     * The notifications sent to the given address about the given resource.
     *
     * @return list<array{toEmail: string, subject: string, message: string, reference: string}>
     */
    public function sentTo(string $toEmail, string $reference): array
    {
        return array_values(array_filter(
            $this->sent,
            static fn (array $sent): bool => $sent['toEmail'] === $toEmail && $sent['reference'] === $reference,
        ));
    }
}
