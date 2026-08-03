@props(['customer' => null, 'lookupResult' => null, 'economicCode' => null])
@php($data = $lookupResult ?? [])
<div class="grid gap-5 md:grid-cols-2">
    <x-form.input name="economic_code" label="کد اقتصادی" :value="$customer?->economic_code ?? $economicCode" inputmode="numeric" required />
    <x-form.input name="national_id" label="شناسه ملی / کد ملی" :value="$customer?->national_id ?? ($data['national_id'] ?? null)" inputmode="numeric" />
    <x-form.input name="name" label="نام مشتری" :value="$customer?->name ?? ($data['name'] ?? null)" required />
    <div>
        <label for="type" class="form-label">نوع مشتری <span class="text-rose-500">*</span></label>
        <select id="type" name="type" class="form-control" required>
            <option value="legal" @selected(old('type', $customer?->type ?? ($data['type'] ?? 'legal')) === 'legal')>حقوقی</option>
            <option value="individual" @selected(old('type', $customer?->type ?? ($data['type'] ?? 'legal')) === 'individual')>حقیقی</option>
        </select>
    </div>
    <x-form.input name="phone" label="شماره تماس" :value="$customer?->phone" dir="ltr" />
    <x-form.input name="postal_code" label="کد پستی" :value="$customer?->postal_code ?? ($data['postal_code'] ?? null)" inputmode="numeric" />
    <x-form.textarea name="address" label="نشانی" :value="$customer?->address ?? ($data['address'] ?? null)" class="md:col-span-2" />
    <div class="md:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer?->is_active ?? true)) class="size-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
            مشتری فعال باشد
        </label>
    </div>
</div>
