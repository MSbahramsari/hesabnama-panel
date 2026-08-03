<?php

namespace App\Models;

use App\Enums\BuyerStatus;
use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'customer_id', 'number', 'invoice_date', 'description', 'status', 'buyer_status', 'subtotal', 'tax_total', 'discount_total', 'total', 'submission_uid', 'tax_id', 'reference_number', 'sent_at', 'last_inquired_at', 'confirmed_at', 'error_message'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'draft', 'subtotal' => 0, 'tax_total' => 0, 'discount_total' => 0, 'total' => 0];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [InvoiceStatus::Draft, InvoiceStatus::PendingSend], true);
    }

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date', 'status' => InvoiceStatus::class, 'buyer_status' => BuyerStatus::class,
            'subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'discount_total' => 'decimal:2',
            'total' => 'decimal:2', 'sent_at' => 'datetime', 'last_inquired_at' => 'datetime', 'confirmed_at' => 'datetime',
        ];
    }
}
