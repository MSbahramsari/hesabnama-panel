<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('invoices') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'invoice_ids' => ['required', 'array', 'min:1', 'max:100'],
            'invoice_ids.*' => ['integer', Rule::exists((new Invoice)->getTable(), 'id')->where('user_id', $this->user()->id)],
        ];
    }
}
