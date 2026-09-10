<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCustomerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'economic_code' => $this->normalizeDigits($this->input('economic_code')),
            'national_id' => $this->normalizeDigits($this->input('national_id')),
            'postal_code' => $this->normalizeDigits($this->input('postal_code')),
            'phone' => $this->normalizeDigits($this->input('phone')),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('customers') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'economic_code' => ['required', 'regex:/^(?:\d{10}|\d{11}|\d{14})$/', Rule::unique((new Customer)->getTable())->where('user_id', $this->user()->id)->ignore($customer)],
            'national_id' => ['nullable', 'digits_between:10,14'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['legal', 'individual'])],
            'address' => ['nullable', 'string', 'max:500'],
            'postal_code' => ['nullable', 'digits:10'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'economic_code.regex' => 'برای شخص حقیقی کد ملی ۱۰ رقمی و برای شخص حقوقی شناسه یا شماره اقتصادی ۱۱ یا ۱۴ رقمی وارد کنید.',
            'national_id.digits_between' => 'شناسه ملی یا کد ملی باید بین ۱۰ تا ۱۴ رقم باشد.',
            'postal_code.digits' => 'کد پستی باید دقیقاً ۱۰ رقم باشد.',
        ];
    }

    private function normalizeDigits(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return strtr(trim($value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
