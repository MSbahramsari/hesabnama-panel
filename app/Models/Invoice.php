<?php

namespace App\Models;

use App\Enums\BuyerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\MoadianStatus;
use App\Enums\SettlementMethod;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'customer_id', 'number', 'invoice_date', 'description', 'invoice_type', 'settlement_method', 'cash_amount', 'reference_invoice_id', 'status', 'moadian_status', 'moadian_tax_result', 'moadian_confirmation_reference_id', 'moadian_packet_type', 'buyer_status', 'buyer_status_source', 'buyer_status_updated_at', 'subtotal', 'tax_total', 'discount_total', 'total', 'submission_uid', 'moadian_serial', 'tax_id', 'reference_number', 'sent_at', 'last_inquired_at', 'confirmed_at', 'error_message'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'draft', 'invoice_type' => 'original', 'settlement_method' => 'cash', 'cash_amount' => 0, 'subtotal' => 0, 'tax_total' => 0, 'discount_total' => 0, 'total' => 0];

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

    public function referenceInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reference_invoice_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(self::class, 'reference_invoice_id');
    }

    public function isEditable(): bool
    {
        return $this->invoice_type !== InvoiceType::Cancellation
            && in_array($this->status, [InvoiceStatus::Draft, InvoiceStatus::PendingSend, InvoiceStatus::MoadianError], true);
    }

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date', 'status' => InvoiceStatus::class, 'moadian_status' => MoadianStatus::class, 'buyer_status' => BuyerStatus::class,
            'invoice_type' => InvoiceType::class, 'settlement_method' => SettlementMethod::class,
            'moadian_serial' => 'integer',
            'cash_amount' => 'decimal:2',
            'subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'discount_total' => 'decimal:2',
            'total' => 'decimal:2', 'sent_at' => 'datetime', 'last_inquired_at' => 'datetime', 'confirmed_at' => 'datetime', 'buyer_status_updated_at' => 'datetime',
        ];
    }
}
