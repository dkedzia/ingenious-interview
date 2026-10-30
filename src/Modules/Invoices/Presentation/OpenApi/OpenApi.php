<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Invoice API',
    description: 'Create, view, and send invoices. Delivery is confirmed by the notification webhook.',
)]
#[OA\Server(url: 'http://localhost', description: 'Local Sail')]
#[OA\Tag(name: 'Invoices', description: 'Invoice lifecycle')]
#[OA\Tag(name: 'Notifications', description: 'Dummy provider webhook')]
#[OA\Schema(
    schema: 'InvoiceStatus',
    type: 'string',
    enum: ['draft', 'sending', 'sent-to-client'],
)]
#[OA\Schema(
    schema: 'ProductLine',
    required: ['name', 'quantity', 'unit_price', 'total_unit_price'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Widget'),
        new OA\Property(property: 'quantity', type: 'integer', minimum: 1, example: 2),
        new OA\Property(property: 'unit_price', type: 'integer', minimum: 1, example: 300),
        new OA\Property(
            property: 'total_unit_price',
            type: 'integer',
            example: 600,
            description: 'quantity multiplied by unit_price',
        ),
    ],
)]
#[OA\Schema(
    schema: 'ProductLineInput',
    required: ['name', 'quantity', 'unit_price'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Widget'),
        new OA\Property(
            property: 'quantity',
            type: 'integer',
            minimum: 1,
            example: 2,
            description: 'JSON integer greater than zero. The line total (quantity x unit_price) must fit in PHP int.',
        ),
        new OA\Property(
            property: 'unit_price',
            type: 'integer',
            minimum: 1,
            example: 300,
            description: 'JSON integer greater than zero. The line total (quantity x unit_price) must fit in PHP int.',
        ),
    ],
)]
#[OA\Schema(
    schema: 'Invoice',
    required: ['id', 'status', 'customer_name', 'customer_email', 'product_lines', 'total_price'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'status', ref: '#/components/schemas/InvoiceStatus'),
        new OA\Property(property: 'customer_name', type: 'string', maxLength: 255, example: 'John Doe'),
        new OA\Property(property: 'customer_email', type: 'string', format: 'email', maxLength: 255, example: 'john@example.com'),
        new OA\Property(
            property: 'product_lines',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/ProductLine'),
        ),
        new OA\Property(
            property: 'total_price',
            type: 'integer',
            example: 600,
            description: 'Sum of product line totals',
        ),
    ],
)]
#[OA\Schema(
    schema: 'InvoiceResponse',
    required: ['data'],
    properties: [
        new OA\Property(property: 'data', ref: '#/components/schemas/Invoice'),
    ],
)]
#[OA\Schema(
    schema: 'CreateInvoiceRequest',
    required: ['customer_name', 'customer_email'],
    description: 'A name that is empty after trim, an email rejected by domain rules, a product line total overflow, or an invoice total overflow returns 422 with errors.invoice. The invoice total must fit in PHP int.',
    properties: [
        new OA\Property(property: 'customer_name', type: 'string', maxLength: 255, example: 'John Doe'),
        new OA\Property(property: 'customer_email', type: 'string', format: 'email', maxLength: 255, example: 'john@example.com'),
        new OA\Property(
            property: 'product_lines',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/ProductLineInput'),
        ),
    ],
)]
#[OA\Schema(
    schema: 'MessageResponse',
    required: ['message'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Invoice not found.'),
    ],
)]
#[OA\Schema(
    schema: 'ValidationErrorResponse',
    required: ['message', 'errors'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The customer name field is required. (and 1 more error)'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string'),
            ),
            example: [
                'customer_name' => ['The customer name field is required.'],
                'customer_email' => ['The customer email field is required.'],
            ],
        ),
    ],
)]
final class OpenApi
{
}
