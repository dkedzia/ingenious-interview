<?php

declare(strict_types=1);

namespace Tests\Feature\Notification\Http;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NotificationControllerTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFaker();
    }

    #[Test]
    public function shouldDispatchEventWhenDeliveredHook(): void
    {
        Event::fake([WebhookDeliveredEvent::class]);

        $uri = route('notification.hook', [
            'action' => 'delivered',
            'reference' => $this->faker->uuid(),
        ]);

        $this->getJson($uri)->assertNoContent();

        Event::assertDispatched(WebhookDeliveredEvent::class);
    }

    #[Test]
    public function shouldReturnNotFoundForUnknownAction(): void
    {
        $uri = route('notification.hook', [
            'action' => 'unknownaction',
            'reference' => $this->faker->uuid(),
        ]);

        $this->getJson($uri)->assertNotFound();
    }

    #[Test]
    public function shouldReturnNotFoundForInvalidReference(): void
    {
        $uri = route('notification.hook', [
            'action' => 'delivered',
            'reference' => 'not-a-uuid',
        ]);

        $this->getJson($uri)->assertNotFound();
    }
}
