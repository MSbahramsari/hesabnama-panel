<?php

namespace App\Http\Requests;

use App\Enums\BuyerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBuyerStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['buyer_status' => ['required', Rule::enum(BuyerStatus::class)]];
    }
}
