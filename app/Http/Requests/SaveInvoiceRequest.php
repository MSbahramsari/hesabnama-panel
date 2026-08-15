<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Support\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('invoices') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('invoice_date_jalali')) {
            $this->merge([
                'invoice_date' => JalaliDate::toGregorianDate($this->string('invoice_date_jalali')->toString()),
            ]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $invoice = $this->route('invoice');
        $userId = $this->user()->id;

        return [
            'customer_id' => ['required', Rule::exists((new Customer)->getTable(), 'id')->where('user_id', $userId)],
            'number' => ['required', 'string', 'max:50', Rule::unique((new Invoice)->getTable())->where('user_id', $userId)->ignore($invoice)],
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.good_id' => ['required', Rule::exists((new Good)->getTable(), 'id')->where('user_id', $userId)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'items.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
        ];
    }
}
