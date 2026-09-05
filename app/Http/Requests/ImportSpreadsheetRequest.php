<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ImportSpreadsheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = match (true) {
            $this->routeIs('customers.import') => 'customers',
            $this->routeIs('goods.import') => 'goods',
            $this->routeIs('invoices.import') => 'invoices',
            default => null,
        };

        return $permission !== null && ($this->user()?->hasPermission($permission) ?? false);
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
            'spreadsheet.required' => 'فایل اکسل را انتخاب کنید.',
            'spreadsheet.*' => 'فایل باید با فرمت xlsx یا csv و حداکثر ۲۰ مگابایت باشد.',
        ];
    }
}
