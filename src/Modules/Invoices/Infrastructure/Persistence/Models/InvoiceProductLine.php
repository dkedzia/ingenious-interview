<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $invoice_id
 * @property string $name
 * @property int $quantity
 * @property int $unit_price
 * @property-read int $total_unit_price
 * @property-read Invoice $invoice
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['name', 'quantity', 'unit_price'])]
final class InvoiceProductLine extends Model
{
    use HasUuids;

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return Attribute<int, never> */
    protected function totalUnitPrice(): Attribute
    {
        return Attribute::get(fn (): int => $this->quantity * $this->unit_price);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
        ];
    }
}
