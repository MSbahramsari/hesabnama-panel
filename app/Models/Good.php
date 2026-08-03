<?php

namespace App\Models;

use Database\Factories\GoodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'commodity_code', 'name', 'unit', 'measurement_unit_code', 'unit_price', 'tax_rate', 'is_active'])]
class Good extends Model
{
    /** @use HasFactory<GoodFactory> */
    use HasFactory;

    protected $attributes = ['unit' => 'عدد', 'tax_rate' => 10, 'is_active' => true];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'tax_rate' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
