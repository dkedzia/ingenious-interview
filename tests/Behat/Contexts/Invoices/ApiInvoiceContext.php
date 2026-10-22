<?php

declare(strict_types=1);

namespace Tests\Behat\Contexts\Invoices;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Behat\Support\FailureKind;
use Tests\Behat\Support\InvoiceScenarioState;
use Tests\Behat\Support\LayerFailure;

/**
 * Drives the HTTP API. Requests go through Laravel's HTTP kernel in the same
 * process instead of over the network, so routing, middleware, request
 * validation and JSON responses are all exercised, while the scenario can
 * still share the in-memory database and the fake notification driver.
 */
final class ApiInvoiceContext extends InvoiceContext
{
    /** Request fields mapped to the labels used in the features. */
    private const array FIELD_LABELS = [
        'customer_name' => 'customer name',
        'customer_email' => 'customer email',
        'name' => 'product name',
        'quantity' => 'quantity',
        'unit_price' => 'unit price',
    ];

    public function __construct(
        InvoiceScenarioState $state,
        private readonly Kernel $kernel,
        private readonly UrlGenerator $urls,
    ) {
        parent::__construct($state);
    }

    protected function createInvoice(string $customerName, string $customerEmail, array $productLines): array
    {
        [$status, $body] = $this->request('POST', $this->urls->route('invoices.store'), [
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'product_lines' => array_map(self::toProductLinePayload(...), $productLines),
        ]);

        return match ($status) {
            Response::HTTP_CREATED => $body['data'],
            Response::HTTP_UNPROCESSABLE_ENTITY => throw new LayerFailure(
                FailureKind::InvalidRequest,
                $body['message'],
                array_map(self::toInvalidField(...), array_keys($body['errors'])),
            ),
            default => throw self::unexpected($status, $body),
        };
    }

    protected function viewInvoice(string $invoiceId): array
    {
        [$status, $body] = $this->request('GET', $this->urls->route('invoices.show', ['id' => $invoiceId]));

        return match ($status) {
            Response::HTTP_OK => $body['data'],
            Response::HTTP_NOT_FOUND => throw new LayerFailure(FailureKind::InvoiceNotFound, $body['message']),
            default => throw self::unexpected($status, $body),
        };
    }

    protected function sendInvoice(string $invoiceId): array
    {
        [$status, $body] = $this->request('POST', $this->urls->route('invoices.send', ['id' => $invoiceId]));

        return match ($status) {
            Response::HTTP_OK => $body['data'],
            Response::HTTP_NOT_FOUND => throw new LayerFailure(FailureKind::InvoiceNotFound, $body['message']),
            Response::HTTP_CONFLICT => throw new LayerFailure(FailureKind::InvoiceCannotBeSent, $body['message']),
            Response::HTTP_SERVICE_UNAVAILABLE => throw new LayerFailure(FailureKind::CustomerNotNotified, $body['message']),
            default => throw self::unexpected($status, $body),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{int, array<string, mixed>}
     */
    private function request(string $method, string $uri, array $payload = []): array
    {
        $request = Request::create(
            uri: $uri,
            method: $method,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: $payload === [] ? null : json_encode($payload, JSON_THROW_ON_ERROR),
        );

        $response = $this->kernel->handle($request);
        $this->kernel->terminate($request, $response);

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true) ?? []];
    }

    /**
     * Values are sent the way a client would type them: whole numbers as
     * integers, other numbers as floats and anything else as a string, so
     * the API's own validation gets to reject "1.5" or "two".
     *
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private static function toProductLinePayload(array $row): array
    {
        $line = [
            'name' => $row['product name'],
            'quantity' => self::toJsonValue($row['quantity']),
            'unit_price' => self::toJsonValue($row['unit price']),
        ];

        if (array_key_exists('total unit price', $row)) {
            $line['total_unit_price'] = self::toJsonValue($row['total unit price']);
        }

        return $line;
    }

    private static function toJsonValue(string $value): int|float|string
    {
        return match (true) {
            preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
            is_numeric($value) => (float) $value,
            default => $value,
        };
    }

    /**
     * Turns "product_lines.0.unit_price" into ["unit price", 0].
     *
     * @return array{string, ?int}
     */
    private static function toInvalidField(string $key): array
    {
        if (preg_match('/^product_lines\.(\d+)\.(\w+)$/', $key, $matches) === 1) {
            return [self::FIELD_LABELS[$matches[2]] ?? $matches[2], (int) $matches[1]];
        }

        return [self::FIELD_LABELS[$key] ?? $key, null];
    }

    /** @param array<string, mixed> $body */
    private static function unexpected(int $status, array $body): RuntimeException
    {
        return new RuntimeException(sprintf('Unexpected %d response: %s', $status, json_encode($body, JSON_PRETTY_PRINT)));
    }
}
