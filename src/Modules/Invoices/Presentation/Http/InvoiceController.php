<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http;

use Illuminate\Http\JsonResponse;
use Modules\Invoices\Application\Commands\CreateInvoiceCommand;
use Modules\Invoices\Application\Commands\SendInvoiceCommand;
use Modules\Invoices\Application\DTOs\ProductLineData;
use Modules\Invoices\Application\Handlers\CreateInvoiceHandler;
use Modules\Invoices\Application\Handlers\SendInvoiceHandler;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Presentation\Http\Requests\CreateInvoiceRequest;
use Modules\Invoices\Presentation\Http\Resources\InvoiceResource;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

final readonly class InvoiceController
{
    public function __construct(
        private CreateInvoiceHandler $createHandler,
        private SendInvoiceHandler $sendHandler,
        private InvoiceRepositoryInterface $repository,
    ) {}

    #[OA\Get(
        path: '/api/invoices/{invoiceId}',
        operationId: 'showInvoice',
        summary: 'View invoice',
        tags: ['Invoices'],
        parameters: [
            new OA\Parameter(
                name: 'invoiceId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Invoice',
                content: new OA\JsonContent(ref: '#/components/schemas/InvoiceResponse'),
            ),
            new OA\Response(
                response: 404,
                description: 'Invoice not found, or invoiceId is not a UUID',
                content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse'),
            ),
        ],
    )]
    public function show(string $invoiceId): InvoiceResource
    {
        $invoice = $this->repository->get(Uuid::fromString($invoiceId));

        return new InvoiceResource($invoice);
    }

    #[OA\Post(
        path: '/api/invoices',
        operationId: 'createInvoice',
        summary: 'Create invoice',
        description: 'Creates an invoice in draft. Product lines may be omitted.',
        tags: ['Invoices'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreateInvoiceRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Created draft invoice',
                content: new OA\JsonContent(ref: '#/components/schemas/InvoiceResponse'),
            ),
            new OA\Response(
                response: 422,
                description: 'Laravel validation errors',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse'),
            ),
        ],
    )]
    public function store(CreateInvoiceRequest $request): JsonResponse
    {
        $command = new CreateInvoiceCommand(
            customerName: $request->validated('customer_name'),
            customerEmail: $request->validated('customer_email'),
            productLines: array_map(
                fn (array $line): ProductLineData => new ProductLineData(
                    name: $line['name'],
                    quantity: $line['quantity'],
                    unitPrice: $line['unit_price'],
                ),
                $request->validated('product_lines', []),
            ),
        );

        $invoice = $this->createHandler->handle($command);

        return (new InvoiceResource($invoice))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OA\Post(
        path: '/api/invoices/{invoiceId}/send',
        operationId: 'sendInvoice',
        summary: 'Send invoice',
        description: 'Marks a draft invoice as sending and notifies the customer. Requires at least one product line. Domain rule failures return 422 with a single message, not a Laravel errors map. Notification failure returns 500 and reverts to draft when the status is still sending.',
        tags: ['Invoices'],
        parameters: [
            new OA\Parameter(
                name: 'invoiceId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Invoice is sending',
                content: new OA\JsonContent(ref: '#/components/schemas/InvoiceResponse'),
            ),
            new OA\Response(
                response: 404,
                description: 'Invoice not found, or invoiceId is not a UUID',
                content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse'),
            ),
            new OA\Response(
                response: 422,
                description: 'Domain rule failed (empty product lines or status is not draft)',
                content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse'),
            ),
            new OA\Response(
                response: 500,
                description: 'Notification failed',
                content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse'),
            ),
        ],
    )]
    public function send(string $invoiceId): InvoiceResource
    {
        $id = Uuid::fromString($invoiceId);

        $this->sendHandler->handle(new SendInvoiceCommand(invoiceId: $id));

        return new InvoiceResource($this->repository->get($id));
    }
}
