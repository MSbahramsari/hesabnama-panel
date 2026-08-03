<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('customers') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'economic_code' => ['required', 'digits_between:10,14', Rule::unique((new Customer)->getTable())->where('user_id', $this->user()->id)->ignore($customer)],
            'national_id' => ['nullable', 'digits_between:10,14'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['legal', 'individual'])],
            'address' => ['nullable', 'string', 'max:500'],
            'postal_code' => ['nullable', 'digits:10'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
