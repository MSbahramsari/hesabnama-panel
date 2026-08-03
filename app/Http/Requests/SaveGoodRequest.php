<?php

namespace App\Http\Requests;

use App\Models\Good;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveGoodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('goods') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $good = $this->route('good');

        return [
            'commodity_code' => ['required', 'digits_between:8,20', Rule::unique((new Good)->getTable())->where('user_id', $this->user()->id)->ignore($good)],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:40'],
            'measurement_unit_code' => ['nullable', 'digits_between:1,10'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
