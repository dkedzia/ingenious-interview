<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $customer_name
 * @property string $customer_email
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ProductLineModel> $productLines
 */
final class InvoiceModel extends Model
{
    protected $table = 'invoices';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'customer_name',
        'customer_email',
        'status',
    ];

    /** @return HasMany<ProductLineModel, $this> */
    public function productLines(): HasMany
    {
        return $this->hasMany(ProductLineModel::class, 'invoice_id');
    }
}
