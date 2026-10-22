<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Domain\Entities;

use Modules\Invoices\Domain\Entities\Invoice;
use Modules\Invoices\Domain\Entities\ProductLine;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidInvoiceException;
use Modules\Invoices\Domain\Exceptions\InvoiceCannotBeSentException;
use Modules\Invoices\Domain\Exceptions\InvoiceDeliveryCannotBeConfirmedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class InvoiceTest extends TestCase
{
    public function test_it_keeps_the_given_id_and_customer_details(): void
    {
        $id = Uuid::uuid7();

        $invoice = new Invoice($id, 'Jane Doe', 'jane.doe@example.com');

        $this->assertTrue($invoice->id->equals($id));
        $this->assertSame('Jane Doe', $invoice->customerName);
        $this->assertSame('jane.doe@example.com', $invoice->customerEmail);
    }

    public function test_a_new_invoice_is_a_draft(): void
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com');

        $this->assertSame(StatusEnum::Draft, $invoice->status);
    }

    public function test_it_can_have_no_product_lines(): void
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com');

        $this->assertSame([], $invoice->productLines);
        $this->assertSame(0, $invoice->totalPrice());
    }

    public function test_it_keeps_product_lines_in_the_given_order(): void
    {
        $keyboard = new ProductLine('Keyboard', 2, 150);
        $mouse = new ProductLine('Mouse', 3, 10);

        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com', $keyboard, $mouse);

        $this->assertSame([$keyboard, $mouse], $invoice->productLines);
    }

    public function test_the_total_price_is_the_sum_of_the_product_line_totals(): void
    {
        $invoice = new Invoice(
            Uuid::uuid7(),
            'Jane Doe',
            'jane.doe@example.com',
            new ProductLine('Keyboard', 2, 150),
            new ProductLine('Mouse', 3, 10),
        );

        $this->assertSame(330, $invoice->totalPrice());
    }

    public function test_sending_a_draft_invoice_with_product_lines_marks_it_as_sending(): void
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com', new ProductLine('Keyboard', 2, 150));

        $invoice->sendToCustomer();

        $this->assertSame(StatusEnum::Sending, $invoice->status);
    }

    public function test_an_invoice_without_product_lines_cannot_be_sent(): void
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com');

        try {
            $invoice->sendToCustomer();

            $this->fail('Expected the invoice not to be sendable.');
        } catch (InvoiceCannotBeSentException) {
            $this->assertSame(StatusEnum::Draft, $invoice->status);
        }
    }

    public function test_an_invoice_that_is_not_a_draft_cannot_be_sent(): void
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com', new ProductLine('Keyboard', 2, 150));
        $invoice->sendToCustomer();

        try {
            $invoice->sendToCustomer();

            $this->fail('Expected the invoice not to be sendable.');
        } catch (InvoiceCannotBeSentException) {
            $this->assertSame(StatusEnum::Sending, $invoice->status);
        }
    }

    public function test_confirming_the_delivery_of_an_invoice_being_sent_marks_it_as_sent_to_client(): void
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com', new ProductLine('Keyboard', 2, 150));
        $invoice->sendToCustomer();

        $invoice->confirmDelivery();

        $this->assertSame(StatusEnum::SentToClient, $invoice->status);
    }

    public function test_the_delivery_of_a_draft_invoice_cannot_be_confirmed(): void
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com', new ProductLine('Keyboard', 2, 150));

        try {
            $invoice->confirmDelivery();

            $this->fail('Expected the delivery not to be confirmable.');
        } catch (InvoiceDeliveryCannotBeConfirmedException) {
            $this->assertSame(StatusEnum::Draft, $invoice->status);
        }
    }

    public function test_the_delivery_of_an_invoice_cannot_be_confirmed_twice(): void
    {
        $invoice = new Invoice(Uuid::uuid7(), 'Jane Doe', 'jane.doe@example.com', new ProductLine('Keyboard', 2, 150));
        $invoice->sendToCustomer();
        $invoice->confirmDelivery();

        try {
            $invoice->confirmDelivery();

            $this->fail('Expected the delivery not to be confirmable.');
        } catch (InvoiceDeliveryCannotBeConfirmedException) {
            $this->assertSame(StatusEnum::SentToClient, $invoice->status);
        }
    }

    #[DataProvider('blankCustomerNames')]
    public function test_the_customer_name_must_not_be_blank(string $customerName): void
    {
        $this->expectException(InvalidInvoiceException::class);

        new Invoice(Uuid::uuid7(), $customerName, 'jane.doe@example.com');
    }

    /** @return iterable<string, array{string}> */
    public static function blankCustomerNames(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'tabs and newlines' => ["\t\n"];
    }

    #[DataProvider('invalidCustomerEmails')]
    public function test_the_customer_email_must_be_valid(string $customerEmail): void
    {
        $this->expectException(InvalidInvoiceException::class);

        new Invoice(Uuid::uuid7(), 'Jane Doe', $customerEmail);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCustomerEmails(): iterable
    {
        yield 'empty' => [''];
        yield 'missing @' => ['jane.doe'];
        yield 'missing domain' => ['jane.doe@'];
        yield 'missing local part' => ['@example.com'];
        yield 'contains a space' => ['jane doe@example.com'];
    }
}
