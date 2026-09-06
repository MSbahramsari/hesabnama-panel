<?php

namespace App\Http\Requests;

use App\Enums\InvoiceType;
use App\Enums\SettlementMethod;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Services\Moadian\InvoiceSubmissionValidator;
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
        if (! $this->filled('settlement_method')) {
            $this->merge(['settlement_method' => SettlementMethod::Cash->value]);
        }

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
        $referenceInvoice = $invoice instanceof Invoice && $invoice->invoice_type === InvoiceType::Correction
            ? Invoice::query()->find($invoice->reference_invoice_id)
            : null;
        $referenceGoodIds = $invoice instanceof Invoice && $invoice->invoice_type === InvoiceType::Correction
            ? $referenceInvoice?->items()->pluck('good_id')->filter()->all()
            : null;
        $customerRules = ['required', Rule::exists((new Customer)->getTable(), 'id')->where('user_id', $userId)];
        $submissionWindowDays = (int) config('services.moadian.normal_submission_window_days', InvoiceSubmissionValidator::NORMAL_SUBMISSION_WINDOW_DAYS);

        if ($invoice instanceof Invoice && $invoice->invoice_type === InvoiceType::Correction) {
            $customerRules[] = Rule::in([$referenceInvoice?->customer_id]);
        }

        return [
            'customer_id' => $customerRules,
            'number' => ['required', 'string', 'max:50', Rule::unique((new Invoice)->getTable())->where('user_id', $userId)->ignore($invoice)],
            'invoice_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
                'after_or_equal:'.today('Asia/Tehran')->subDays($submissionWindowDays)->format('Y-m-d'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'settlement_method' => ['required', Rule::enum(SettlementMethod::class)],
            'cash_amount' => ['nullable', 'required_if:settlement_method,mixed', 'numeric', 'gt:0', 'max:9999999999999999'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.good_id' => array_values(array_filter([
                'required',
                Rule::exists((new Good)->getTable(), 'id')->where('user_id', $userId),
                $referenceGoodIds !== null ? Rule::in($referenceGoodIds) : null,
            ])),
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit_price' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'items.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $submissionWindowDays = (int) config('services.moadian.normal_submission_window_days', InvoiceSubmissionValidator::NORMAL_SUBMISSION_WINDOW_DAYS);

        return [
            'invoice_date.before_or_equal' => 'تاریخ صورتحساب نمی‌تواند بعد از تاریخ امروز باشد.',
            'invoice_date.after_or_equal' => "برای ارسال عادی به سامانه مودیان، تاریخ صورتحساب نباید بیشتر از {$submissionWindowDays} روز قبل باشد.",
            'items.*.unit_price.gt' => 'مبلغ واحد هر قلم باید بزرگ‌تر از صفر باشد.',
        ];
    }
}
