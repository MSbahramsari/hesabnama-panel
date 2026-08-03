@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="form-label">{{ $label }} @if($required)<span class="text-rose-500">*</span>@endif</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @required($required)
        {{ $attributes->except(['class'])->merge(['class' => 'form-control '.($errors->has($name) ? 'form-control-error' : '')]) }}>
    @if($hint)<p class="mt-1.5 text-xs text-slate-500">{{ $hint }}</p>@endif
    @error($name)<p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
</div>
