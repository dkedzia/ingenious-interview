<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http;

use Illuminate\Http\JsonResponse;
use Modules\Invoices\Application\Commands\CreateInvoiceCommand;
use Modules\Invoices\Application\Commands\SendInvoiceCommand;
use Modules\Invoices\Application\DTOs\ProductLineData;
use Modules\Invoices\Application\Handlers\CreateInvoiceHandler;
use Modules\Invoices\Application\Handlers\SendInvoiceHandler;
use Modules\Invoices\Domain\Exceptions\EmptyProductLinesException;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Repositories\InvoiceRepositoryInterface;
use Modules\Invoices\Presentation\Http\Requests\CreateInvoiceRequest;
use Modules\Invoices\Presentation\Http\Resources\InvoiceResource;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

final readonly class InvoiceController
{
    public function __construct(
        private CreateInvoiceHandler $createHandler,
        private SendInvoiceHandler $sendHandler,
        private InvoiceRepositoryInterface $repository,
    ) {}

    public function show(string $invoiceId): InvoiceResource
    {
        $invoice = $this->repository->get(Uuid::fromString($invoiceId));

        return new InvoiceResource($invoice);
    }

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

    public function send(string $invoiceId): InvoiceResource|JsonResponse
    {
        $id = Uuid::fromString($invoiceId);

        try {
            $this->sendHandler->handle(new SendInvoiceCommand(invoiceId: $id));
        } catch (InvalidStatusTransitionException|EmptyProductLinesException $e) {
            return new JsonResponse(
                ['message' => $e->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $invoice = $this->repository->get($id);

        return new InvoiceResource($invoice);
    }
}
