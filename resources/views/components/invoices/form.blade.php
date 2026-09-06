@props(['invoice' => null, 'customers', 'goods', 'suggestedNumber'])
@php
    $isCorrection = $invoice?->invoice_type === \App\Enums\InvoiceType::Correction;
    $settlementMethod = old('settlement_method', $invoice?->settlement_method?->value ?? \App\Enums\SettlementMethod::Cash->value);
    $initialItems = old('items');
    if ($initialItems === null && $invoice) {
        $initialItems = $invoice->items->map(fn ($item) => [
            'good_id' => $item->good_id,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'tax_rate' => $item->tax_rate,
            'discount' => $item->discount,
        ])->all();
    }
    $initialItems ??= [];
@endphp

@if($invoice && $invoice->invoice_type !== \App\Enums\InvoiceType::Original)
    <div @class(['mb-6 rounded-2xl border p-4 text-sm leading-7', 'border-blue-200 bg-blue-50 text-blue-900' => $isCorrection, 'border-rose-200 bg-rose-50 text-rose-900' => !$isCorrection])>
        <strong>صورتحساب {{ $invoice->invoice_type->label() }}</strong>
        @if($invoice->referenceInvoice)
            <span>— مرجع: {{ $invoice->referenceInvoice->number }} / {{ $invoice->referenceInvoice->tax_id }}</span>
        @endif
    </div>
@endif

<div class="grid gap-5 md:grid-cols-3">
    <div>
        <label for="customer_id" class="form-label">مشتری <span class="text-rose-500">*</span></label>
        @if($isCorrection)
            <input type="hidden" name="customer_id" value="{{ $invoice->customer_id }}">
            <div class="form-control bg-slate-100 text-slate-500">{{ $invoice->referenceInvoice->customer->name ?? $customers->firstWhere('id', $invoice->customer_id)?->name }}</div>
            <p class="mt-2 text-[10px] text-slate-400">خریدار صورتحساب اصلاحی باید با مرجع یکسان باشد.</p>
        @else
            <select id="customer_id" name="customer_id" class="form-control" required><option value="">انتخاب مشتری</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string) old('customer_id', $invoice?->customer_id) === (string) $customer->id)>{{ $customer->name }} — {{ $customer->economic_code }}</option>@endforeach</select>
            <a href="{{ route('customers.create') }}" class="mt-2 inline-flex text-xs font-bold text-teal-700">+ افزودن سریع مشتری</a>
        @endif
        @error('customer_id')<p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
    </div>
    <x-form.input name="number" label="شماره صورتحساب" :value="$invoice?->number ?? $suggestedNumber" required />
    <x-form.jalali-date name="invoice_date" label="تاریخ صورتحساب" :value="$invoice?->invoice_date ?? today()" required />
    <div>
        <label for="settlement_method" class="form-label">روش تسویه <span class="text-rose-500">*</span></label>
        <select id="settlement_method" name="settlement_method" class="form-control" data-settlement-method required>
            @foreach(\App\Enums\SettlementMethod::cases() as $method)
                <option value="{{ $method->value }}" @selected($settlementMethod === $method->value)>{{ $method->label() }}</option>
            @endforeach
        </select>
        @error('settlement_method')<p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div data-cash-amount-field @class(['hidden' => $settlementMethod !== \App\Enums\SettlementMethod::Mixed->value])>
        <x-form.input name="cash_amount" label="مبلغ پرداختی نقدی (بدون مالیات)" type="text" inputmode="numeric" data-money-input :value="$invoice?->settlement_method === \App\Enums\SettlementMethod::Mixed ? number_format((float) $invoice->cash_amount, 0, '.', ',') : null" />
    </div>
    <x-form.textarea name="description" label="توضیحات" :value="$invoice?->description" class="md:col-span-3" />
</div>

<div class="my-7 border-t border-slate-100"></div>
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div><h3 class="card-title">اقلام صورتحساب</h3><p class="card-subtitle">مقدار، قیمت، مالیات و تخفیف هر ردیف را مشخص کنید.</p></div>
    <button type="button" class="btn-secondary" data-add-invoice-item><x-icon name="plus" class="size-4" />افزودن ردیف</button>
</div>

@error('items')<p class="mt-3 text-sm font-semibold text-rose-600">{{ $message }}</p>@enderror
<div class="mt-5 space-y-3" data-invoice-items>
    <div data-invoice-items-empty @class(['rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center', 'hidden' => count($initialItems) > 0])>
        <div class="font-extrabold text-slate-700">هنوز قلمی به صورتحساب اضافه نشده است.</div>
        <p class="mt-1 text-xs leading-6 text-slate-500">برای انتخاب کالا یا خدمت، روی «افزودن ردیف» بزنید.</p>
    </div>
    @foreach($initialItems as $index => $item)
        <div class="invoice-item-row" data-invoice-item>
            <div class="invoice-item-number">{{ $loop->iteration }}</div>
            <div class="min-w-0 sm:col-span-2 lg:col-span-3"><label class="form-label">کالا / خدمت</label><select name="items[{{ $index }}][good_id]" class="form-control" data-good-select required><option value="">انتخاب قلم</option>@foreach($goods as $good)<option value="{{ $good->id }}" data-price="{{ $good->unit_price }}" data-tax="{{ $good->tax_rate }}" @selected((string) ($item['good_id'] ?? '') === (string) $good->id)>{{ $good->name }} — {{ $good->commodity_code }}</option>@endforeach</select></div>
            <div><label class="form-label">تعداد</label><input name="items[{{ $index }}][quantity]" type="number" min="0.001" step="0.001" value="{{ $item['quantity'] ?? 1 }}" class="form-control" data-quantity required></div>
            <div class="lg:col-span-2"><label class="form-label">قیمت واحد</label><input name="items[{{ $index }}][unit_price]" type="text" inputmode="numeric" value="{{ number_format((float) ($item['unit_price'] ?? 0), 0, '.', ',') }}" class="form-control" data-money-input data-unit-price required></div>
            <div><label class="form-label">مالیات ٪</label><input name="items[{{ $index }}][tax_rate]" type="number" min="0" max="100" step="0.01" value="{{ $item['tax_rate'] ?? 10 }}" class="form-control" data-tax-rate required></div>
            <div><label class="form-label">تخفیف</label><input name="items[{{ $index }}][discount]" type="text" inputmode="numeric" value="{{ number_format((float) ($item['discount'] ?? 0), 0, '.', ',') }}" class="form-control" data-money-input data-discount></div>
            <div class="flex items-end justify-between gap-3 lg:block"><div><div class="form-label">جمع ردیف</div><div class="pb-3 text-sm font-black text-slate-900" data-line-total>۰ ریال</div></div><button type="button" class="mb-1 rounded-xl p-2 text-rose-500 transition hover:bg-rose-50" data-remove-invoice-item title="حذف ردیف">حذف</button></div>
        </div>
    @endforeach
</div>

<template id="invoice-item-template">
    <div class="invoice-item-row" data-invoice-item>
        <div class="invoice-item-number">#</div>
        <div class="min-w-0 sm:col-span-2 lg:col-span-3"><label class="form-label">کالا / خدمت</label><select class="form-control" data-field="good_id" data-good-select required><option value="">انتخاب قلم</option>@foreach($goods as $good)<option value="{{ $good->id }}" data-price="{{ $good->unit_price }}" data-tax="{{ $good->tax_rate }}">{{ $good->name }} — {{ $good->commodity_code }}</option>@endforeach</select></div>
        <div><label class="form-label">تعداد</label><input type="number" min="0.001" step="0.001" value="1" class="form-control" data-field="quantity" data-quantity required></div>
        <div class="lg:col-span-2"><label class="form-label">قیمت واحد</label><input type="text" inputmode="numeric" value="0" class="form-control" data-field="unit_price" data-money-input data-unit-price required></div>
        <div><label class="form-label">مالیات ٪</label><input type="number" min="0" max="100" step="0.01" value="10" class="form-control" data-field="tax_rate" data-tax-rate required></div>
        <div><label class="form-label">تخفیف</label><input type="text" inputmode="numeric" value="0" class="form-control" data-field="discount" data-money-input data-discount></div>
        <div class="flex items-end justify-between gap-3 lg:block"><div><div class="form-label">جمع ردیف</div><div class="pb-3 text-sm font-black text-slate-900" data-line-total>۰ ریال</div></div><button type="button" class="mb-1 rounded-xl p-2 text-rose-500 transition hover:bg-rose-50" data-remove-invoice-item>حذف</button></div>
    </div>
</template>

<div class="mt-7 flex justify-end">
    <div class="w-full rounded-2xl bg-slate-900 p-5 text-white sm:max-w-md">
        <div class="flex justify-between py-2 text-sm text-slate-300"><span>جمع اقلام</span><strong data-invoice-subtotal>۰ ریال</strong></div>
        <div class="flex justify-between py-2 text-sm text-slate-300"><span>تخفیف</span><strong data-invoice-discount>۰ ریال</strong></div>
        <div class="flex justify-between py-2 text-sm text-slate-300"><span>مالیات</span><strong data-invoice-tax>۰ ریال</strong></div>
        <div class="mt-2 flex justify-between border-t border-white/10 pt-4 text-base"><span class="font-bold">مبلغ نهایی</span><strong class="text-lg text-teal-300" data-invoice-total>۰ ریال</strong></div>
    </div>
</div>
