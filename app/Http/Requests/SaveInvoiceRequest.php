<?php

namespace App\Http\Requests;

use App\Enums\ElectronicInvoiceType;
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
        $this->merge([
            'electronic_invoice_type' => $this->input('electronic_invoice_type', ElectronicInvoiceType::Full->value),
        ]);

        $items = $this->input('items');

        if (is_array($items)) {
            foreach ($items as &$item) {
                if (is_array($item)) {
                    $item['unit_price'] = $this->normalizeMoney($item['unit_price'] ?? null);
                    $item['discount'] = $this->normalizeMoney($item['discount'] ?? null);
                }
            }
            unset($item);

            $this->merge(['items' => $items]);
        }

        if ($this->has('cash_amount')) {
            $this->merge(['cash_amount' => $this->normalizeMoney($this->input('cash_amount'))]);
        }

        if (! $this->filled('settlement_method')) {
            $this->merge(['settlement_method' => SettlementMethod::Cash->value]);
        }

        if ((int) $this->input('electronic_invoice_type', 1) !== ElectronicInvoiceType::Full->value) {
            $this->merge(['settlement_method' => SettlementMethod::Cash->value]);
        }

        if ($this->has('invoice_date_jalali')) {
            $this->merge([
                'invoice_date' => JalaliDate::toGregorianDate($this->string('invoice_date_jalali')->toString()),
            ]);
        }
    }

    private function normalizeMoney(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return str_replace([',', '٬', ' '], '', strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]));
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
        $electronicType = (int) $this->input('electronic_invoice_type', ElectronicInvoiceType::Full->value);
        $customerRules = [Rule::requiredIf($electronicType === ElectronicInvoiceType::Full->value), 'nullable', Rule::exists((new Customer)->getTable(), 'id')->where('user_id', $userId)];
        $submissionWindowDays = (int) config('services.moadian.normal_submission_window_days', InvoiceSubmissionValidator::NORMAL_SUBMISSION_WINDOW_DAYS);

        if ($invoice instanceof Invoice && $invoice->invoice_type === InvoiceType::Correction) {
            $customerRules[] = Rule::in([$referenceInvoice?->customer_id]);
        }

        $electronicTypeRules = ['required', Rule::enum(ElectronicInvoiceType::class)];

        if ($invoice instanceof Invoice && $invoice->invoice_type !== InvoiceType::Original) {
            $electronicTypeRules[] = Rule::in([$invoice->electronic_invoice_type->value]);
        }

        return [
            'customer_id' => $customerRules,
            'electronic_invoice_type' => $electronicTypeRules,
            'number' => ['required', 'string', 'max:50', Rule::unique((new Invoice)->getTable())->where('user_id', $userId)->ignore($invoice)],
            'invoice_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
                'after_or_equal:'.today('Asia/Tehran')->subDays($submissionWindowDays)->format('Y-m-d'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'settlement_method' => ['required', Rule::enum(SettlementMethod::class)],
            'cash_amount' => ['exclude_unless:settlement_method,mixed', 'required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'payment_switch_number' => ['required_if:electronic_invoice_type,3', 'nullable', 'digits:9'],
            'payment_acceptor_number' => ['required_if:electronic_invoice_type,3', 'nullable', 'digits:14'],
            'payment_terminal_number' => ['required_if:electronic_invoice_type,3', 'nullable', 'digits:8'],
            'payment_method' => ['required_if:electronic_invoice_type,3', 'nullable', 'integer', Rule::in(range(1, 8))],
            'payment_tracking_number' => ['required_if:electronic_invoice_type,3', 'nullable', 'digits_between:1,14'],
            'payer_card_number' => ['required_if:electronic_invoice_type,3', 'nullable', 'digits:16'],
            'payer_identifier' => ['required_if:electronic_invoice_type,3', 'nullable', 'digits_between:10,12'],
            'payment_at' => ['required_if:electronic_invoice_type,3', 'nullable', 'date'],
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
            'cash_amount.required' => 'در روش نقدی / نسیه، مبلغ پرداختی نقدی را وارد کنید.',
            'cash_amount.numeric' => 'مبلغ پرداختی نقدی باید عدد باشد.',
            'cash_amount.gt' => 'مبلغ پرداختی نقدی باید بزرگ‌تر از صفر باشد.',
            'items.*.unit_price.gt' => 'مبلغ واحد هر قلم باید بزرگ‌تر از صفر باشد.',
        ];
    }
}
