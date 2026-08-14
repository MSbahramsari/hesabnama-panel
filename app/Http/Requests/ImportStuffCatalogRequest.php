<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ImportStuffCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'catalog_files' => ['required', 'array', 'min:1', 'max:4'],
            'catalog_files.*' => ['required', File::types(['csv', 'txt'])->max('500mb')],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'catalog_files' => 'فایل‌های کاتالوگ',
            'catalog_files.*' => 'فایل کاتالوگ',
        ];
    }
}
