@if(session('success'))
    <div class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 shadow-sm" role="status">
        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-emerald-500 text-white"><x-icon name="check" class="size-4" /></span>
        <span class="pt-1 font-semibold">{{ session('success') }}</span>
    </div>
@endif

@if(session('error'))
    <div class="mb-6 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 shadow-sm" role="alert">
        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-rose-500 text-white"><x-icon name="warning" class="size-4" /></span>
        <span class="pt-1 font-semibold">{{ session('error') }}</span>
    </div>
@endif

@if($errors->any())
    <div class="mb-6 flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 shadow-sm" role="alert">
        <span class="grid size-7 shrink-0 place-items-center rounded-full bg-rose-500 text-white"><x-icon name="warning" class="size-4" /></span>
        <div>
            <div class="font-bold">لطفاً خطاهای فرم را بررسی کنید.</div>
            <div class="mt-1 text-rose-700">{{ $errors->first() }}</div>
        </div>
    </div>
@endif
