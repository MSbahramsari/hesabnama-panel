<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invoice_id', 'good_id', 'description', 'commodity_code', 'quantity', 'unit_price', 'tax_rate', 'discount', 'subtotal', 'tax_amount', 'total'])]
class InvoiceItem extends Model
{
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'tax_rate' => 'decimal:2',
            'discount' => 'decimal:2', 'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2',
        ];
    }
}
