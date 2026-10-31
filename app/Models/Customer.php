<?php

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'economic_code', 'national_id', 'name', 'type', 'address', 'postal_code', 'branch_code', 'phone', 'is_active'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $attributes = ['type' => 'legal', 'is_active' => true];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function moadianEconomicCode(): string
    {
        $economicCode = (string) $this->economic_code;

        if ($this->type !== 'individual' || strlen($economicCode) === 14) {
            return $economicCode;
        }

        $nationalId = $this->moadianNationalIdentifier();

        return strlen($nationalId) === 10 ? $nationalId.'0001' : $economicCode;
    }

    public function moadianNationalIdentifier(): string
    {
        $nationalId = (string) $this->national_id;

        if ($this->type === 'individual' && strlen($nationalId) !== 10) {
            $economicCode = (string) $this->economic_code;

            if (in_array(strlen($economicCode), [10, 14], true)) {
                return substr($economicCode, 0, 10);
            }
        }

        return $nationalId;
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
