<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Http;

use Illuminate\Http\JsonResponse;
use Modules\Notifications\Application\Services\NotificationService;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class NotificationController
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    #[OA\Get(
        path: '/api/notification/hook/{action}/{reference}',
        operationId: 'notificationHook',
        summary: 'Notification delivery webhook',
        description: 'The dummy provider calls this after delivery. action=delivered dispatches WebhookDeliveredEvent. The invoice listener marks sent-to-client only when the invoice is sending. Unknown ids and other statuses are ignored. Other actions return 404.',
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(
                name: 'action',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'delivered'),
            ),
            new OA\Parameter(
                name: 'reference',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Event dispatched'),
            new OA\Response(
                response: 404,
                description: 'Unknown action, or reference is not a UUID',
            ),
        ],
    )]
    public function hook(string $action, string $reference): JsonResponse
    {
        match ($action) {
            'delivered' => $this->notificationService->delivered(
                reference: Uuid::fromString($reference),
            ),
            default => throw new NotFoundHttpException("Unknown notification action: {$action}"),
        };

        return new JsonResponse(data: null, status: Response::HTTP_NO_CONTENT);
    }
}
