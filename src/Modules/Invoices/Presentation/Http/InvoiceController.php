<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSentException;
use Modules\Invoices\Domain\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Presentation\Http\Requests\CreateInvoiceFormRequest;
use Modules\Invoices\Presentation\Http\Resources\InvoiceResource;
use Modules\Invoices\UseCase\CreateInvoice;
use Modules\Invoices\UseCase\Exceptions\InvalidCreateInvoiceRequestException;
use Modules\Invoices\UseCase\Exceptions\InvoiceNotSentException;
use Modules\Invoices\UseCase\SendInvoice;
use Modules\Invoices\UseCase\SendInvoiceRequest;
use Modules\Invoices\UseCase\ViewInvoice;
use Modules\Invoices\UseCase\ViewInvoiceRequest;
use Modules\Invoices\UseCase\Violation;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final readonly class InvoiceController
{
    public function __construct(
        private CreateInvoice $createInvoice,
        private ViewInvoice $viewInvoice,
        private SendInvoice $sendInvoice,
    ) {}

    public function viewInvoice(string $id): InvoiceResource
    {
        try {
            $invoice = $this->viewInvoice->execute(new ViewInvoiceRequest(Uuid::fromString($id)));
        } catch (InvoiceNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }

        return new InvoiceResource($invoice);
    }

    /** @throws ValidationException */
    public function createInvoice(CreateInvoiceFormRequest $request): JsonResponse
    {
        try {
            $invoice = $this->createInvoice->execute($request->toUseCaseRequest());
        } catch (InvalidCreateInvoiceRequestException $exception) {
            throw ValidationException::withMessages(self::errorsFrom($exception->violations));
        }

        return new InvoiceResource($invoice)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function sendInvoice(string $id): InvoiceResource
    {
        try {
            $invoice = $this->sendInvoice->execute(new SendInvoiceRequest(Uuid::fromString($id)));
        } catch (InvoiceNotFoundException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        } catch (InvoiceCannotBeSentException $exception) {
            throw new ConflictHttpException($exception->getMessage(), $exception);
        } catch (InvoiceNotSentException $exception) {
            throw new ServiceUnavailableHttpException(message: $exception->getMessage(), previous: $exception);
        }

        return new InvoiceResource($invoice);
    }

    /**
     * Keys the violations by the request field they concern, e.g.
     * "product_lines.0.unit_price", the same way the request's own errors are.
     *
     * @param  list<Violation>  $violations
     * @return array<string, list<string>>
     */
    private static function errorsFrom(array $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            $field = Str::snake($violation->field);
            $key = $violation->productLineIndex === null
                ? $field
                : "product_lines.{$violation->productLineIndex}.{$field}";

            $errors[$key][] = $violation->message;
        }

        return $errors;
    }
}
