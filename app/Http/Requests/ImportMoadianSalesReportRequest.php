<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ImportMoadianSalesReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('invoices') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'spreadsheet' => ['required', File::types(['xlsx', 'csv'])->max(20 * 1024)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'spreadsheet.required' => 'فایل خروجی فروش داخلی سامانه مودیان را انتخاب کنید.',
            'spreadsheet.*' => 'فایل باید با فرمت xlsx یا csv و حداکثر ۲۰ مگابایت باشد.',
        ];
    }
}
