<?php

namespace App\Models;

use Database\Factories\TaxpayerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'taxpayer_name', 'taxpayer_type', 'national_id', 'economic_code', 'fiscal_id', 'branch_code', 'private_key', 'connection_verified_at'])]
#[Hidden(['private_key'])]
class TaxpayerProfile extends Model
{
    /** @use HasFactory<TaxpayerProfileFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'private_key' => 'encrypted',
            'connection_verified_at' => 'datetime',
        ];
    }
}
