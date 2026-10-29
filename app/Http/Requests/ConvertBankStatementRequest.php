<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class ConvertBankStatementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('convert-bank-statements') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'statement' => ['required', File::types(['xls', 'xlsx'])->max(30 * 1024)],
            'bank_moein_code' => ['required', 'string', 'max:50'],
            'bank_tafsili_code' => ['nullable', 'string', 'max:50'],
            'counterpart_moein_code' => ['required', 'string', 'max:50'],
            'counterpart_tafsili_code' => ['nullable', 'string', 'max:50'],
            'starting_voucher_number' => ['required', 'integer', 'min:1', 'max:999999999'],
            'grouping' => ['required', Rule::in(['daily', 'transaction'])],
            'document_description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'statement' => 'فایل صورتحساب بانکی',
            'bank_moein_code' => 'کد معین حساب بانکی',
            'bank_tafsili_code' => 'کد تفصیلی حساب بانکی',
            'counterpart_moein_code' => 'کد معین حساب مقابل',
            'counterpart_tafsili_code' => 'کد تفصیلی حساب مقابل',
            'starting_voucher_number' => 'شماره شروع سند',
            'grouping' => 'نحوه گروه‌بندی اسناد',
            'document_description' => 'توضیحات سند',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'statement.required' => 'فایل صورتحساب بانکی را انتخاب کنید.',
            'statement.*' => 'فایل باید با فرمت xls یا xlsx و حداکثر ۳۰ مگابایت باشد.',
        ];
    }
}
