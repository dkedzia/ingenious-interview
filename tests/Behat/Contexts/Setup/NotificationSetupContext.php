<?php

declare(strict_types=1);

namespace Tests\Behat\Contexts\Setup;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Tests\Behat\Support\FakeNotificationDriver;

final readonly class NotificationSetupContext implements Context
{
    public function __construct(
        private FakeNotificationDriver $notificationDriver,
    ) {}

    #[Given('the notification provider is unavailable')]
    public function theNotificationProviderIsUnavailable(): void
    {
        $this->notificationDriver->available = false;
    }

    #[Given('the notification provider is available again')]
    public function theNotificationProviderIsAvailableAgain(): void
    {
        $this->notificationDriver->available = true;
    }
}
