<?php

declare(strict_types=1);

namespace Tests\Behat\Contexts\Invoices;

use Behat\Behat\Context\Context;
use Behat\Step\Then;
use Modules\Invoices\Infrastructure\Persistence\Models\Invoice as InvoiceModel;
use PHPUnit\Framework\Assert;

/**
 * Checks storage directly, for facts no layer exposes (there is no endpoint
 * or use case that lists invoices).
 */
final readonly class InvoiceStorageContext implements Context
{
    #[Then('there should be :count invoices')]
    public function thereShouldBeInvoices(string $count): void
    {
        Assert::assertSame((int) $count, InvoiceModel::query()->count());
    }
}
