<?php

namespace App\Http\Requests;

use App\Models\Good;
use App\Support\MeasurementUnitCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveGoodRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'measurement_unit_code' => MeasurementUnitCode::resolve($this->input('measurement_unit_code')),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('goods') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $good = $this->route('good');

        return [
            'commodity_code' => ['required', 'digits:13', Rule::unique((new Good)->getTable())->where('user_id', $this->user()->id)->ignore($good)],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:40'],
            'measurement_unit_code' => ['required', 'digits_between:1,8'],
            'unit_price' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
