@props(['name', 'label', 'value' => null, 'rows' => 3, 'required' => false])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="form-label">{{ $label }} @if($required)<span class="text-rose-500">*</span>@endif</label>
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" @required($required)
        {{ $attributes->except(['class'])->merge(['class' => 'form-control '.($errors->has($name) ? 'form-control-error' : '')]) }}>{{ old($name, $value) }}</textarea>
    @error($name)<p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
</div>
