@props(['good' => null, 'lookupResult' => null, 'commodityCode' => null])
@php($data = $lookupResult ?? [])
<div class="grid gap-5 md:grid-cols-2">
    <x-form.input name="commodity_code" label="شناسه کالا / خدمت" :value="$good?->commodity_code ?? $commodityCode" inputmode="numeric" required />
    <x-form.input name="name" label="عنوان کالا / خدمت" :value="$good?->name ?? ($data['name'] ?? null)" required />
    <x-form.input name="unit" label="واحد اندازه‌گیری" :value="$good?->unit ?? ($data['unit'] ?? 'عدد')" required />
    <x-form.input name="measurement_unit_code" label="کد واحد اندازه‌گیری مودیان" :value="$good?->measurement_unit_code ?? ($data['measurement_unit_code'] ?? null)" inputmode="numeric" />
    <x-form.input name="unit_price" label="قیمت واحد (ریال)" type="number" :value="$good?->unit_price ?? ($data['unit_price'] ?? null)" min="0" required />
    <x-form.input name="tax_rate" label="نرخ مالیات (درصد)" type="number" :value="$good?->tax_rate ?? ($data['tax_rate'] ?? 10)" min="0" max="100" step="0.01" required />
    <div class="flex items-end pb-3">
        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $good?->is_active ?? true)) class="size-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
            قابل استفاده در فاکتور
        </label>
    </div>
</div>
