@props(['name', 'label', 'value' => null, 'required' => false, 'hint' => null])
@php
    $gregorianValue = old($name, $value);
    if ($gregorianValue instanceof \DateTimeInterface) {
        $gregorianValue = $gregorianValue->format('Y-m-d');
    }
    $jalaliValue = old($name.'_jalali') ?? \App\Support\JalaliDate::format($gregorianValue);
@endphp

<div {{ $attributes->only('class') }} data-jalali-date>
    <label for="{{ $name }}_jalali" class="form-label">{{ $label }} @if($required)<span class="text-rose-500">*</span>@endif</label>
    <div class="relative">
        <input id="{{ $name }}_jalali" name="{{ $name }}_jalali" type="text" value="{{ $jalaliValue }}"
            class="form-control pl-12 {{ $errors->has($name) ? 'form-control-error' : '' }}" dir="ltr" inputmode="numeric"
            placeholder="۱۴۰۵/۰۵/۲۴" autocomplete="off" @required($required) data-jalali-input>
        <input id="{{ $name }}" name="{{ $name }}" type="hidden" value="{{ $gregorianValue }}" data-gregorian-input>
        <button type="button" class="absolute left-2 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-xl text-slate-400 hover:bg-teal-50 hover:text-teal-700" aria-label="باز کردن تقویم شمسی" aria-expanded="false" data-jalali-toggle>
            <x-icon name="calendar" class="size-4" />
        </button>
        <div class="jalali-picker-panel hidden" data-jalali-panel>
            <div class="jalali-picker-header">
                <button type="button" class="jalali-picker-nav" aria-label="ماه قبل" data-jalali-prev><x-icon name="arrow-left" class="size-4 rotate-180" /></button>
                <strong data-jalali-title></strong>
                <button type="button" class="jalali-picker-nav" aria-label="ماه بعد" data-jalali-next><x-icon name="arrow-left" class="size-4" /></button>
            </div>
            <div class="jalali-weekdays"><span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span>پ</span><span>ج</span></div>
            <div class="jalali-days" data-jalali-days></div>
            <div class="jalali-picker-footer">
                <button type="button" data-jalali-today>امروز</button>
                @unless($required)<button type="button" class="text-rose-600" data-jalali-clear>پاک کردن</button>@endunless
            </div>
        </div>
    </div>
    @if($hint)<p class="mt-1.5 text-xs text-slate-500">{{ $hint }}</p>@endif
    @error($name)<p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
</div>
