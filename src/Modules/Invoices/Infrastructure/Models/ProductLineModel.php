<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $invoice_id
 * @property string $name
 * @property int $unit_price
 * @property int $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InvoiceModel $invoice
 */
final class ProductLineModel extends Model
{
    protected $table = 'invoice_product_lines';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'unit_price',
        'quantity',
    ];

    /** @return BelongsTo<InvoiceModel, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(InvoiceModel::class, 'invoice_id');
    }
}
