<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Invoices\Domain\Enums\StatusEnum;

/**
 * @property string $id
 * @property string $customer_name
 * @property string $customer_email
 * @property StatusEnum $status
 * @property-read int $total_price
 * @property-read Collection<int, InvoiceProductLine> $productLines
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['customer_name', 'customer_email', 'status'])]
final class Invoice extends Model
{
    use HasUuids;

    /** @return HasMany<InvoiceProductLine, $this> */
    public function productLines(): HasMany
    {
        return $this->hasMany(InvoiceProductLine::class);
    }

    /** @return Attribute<int, never> */
    protected function totalPrice(): Attribute
    {
        return Attribute::get(
            fn (): int => $this->productLines->sum(
                static fn (InvoiceProductLine $line): int => $line->total_unit_price,
            ),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => StatusEnum::class,
        ];
    }
}
