<?php

declare(strict_types=1);

namespace Tests\Behat\Contexts;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;
use Modules\Notifications\Application\Services\NotificationService;
use PHPUnit\Framework\Assert;
use Ramsey\Uuid\Uuid;

/**
 * Both constructor arguments are autowired from the Laravel container
 * (see behat.php), so the context exercises the real service bindings.
 */
final class NotificationDeliveryContext implements Context
{
    /** @var list<WebhookDeliveredEvent> */
    private array $deliveredEvents = [];

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly Dispatcher $events,
    ) {}

    #[Given('the application listens for delivered notifications')]
    public function theApplicationListensForDeliveredNotifications(): void
    {
        $this->events->listen(
            WebhookDeliveredEvent::class,
            fn (WebhookDeliveredEvent $event) => $this->deliveredEvents[] = $event,
        );
    }

    #[When('the notification provider confirms delivery of resource :resourceId')]
    public function theNotificationProviderConfirmsDeliveryOfResource(string $resourceId): void
    {
        $this->notificationService->delivered(Uuid::fromString($resourceId));
    }

    #[Then('a delivered event should be dispatched for resource :resourceId')]
    public function aDeliveredEventShouldBeDispatchedForResource(string $resourceId): void
    {
        Assert::assertCount(1, $this->deliveredEvents);
        Assert::assertTrue($this->deliveredEvents[0]->resourceId->equals(Uuid::fromString($resourceId)));
    }
}
