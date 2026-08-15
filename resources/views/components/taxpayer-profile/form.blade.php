@props(['profile' => null, 'required' => true])

<section id="taxpayer-connection" class="mt-7 scroll-mt-24 border-t border-slate-100 pt-6" data-taxpayer-profile>
    <div class="mb-5">
        <h3 class="card-title">اطلاعات اتصال به سامانه مودیان</h3>
        <p class="card-subtitle">این مشخصات و کلید خصوصی فقط برای پرونده مالیاتی همین حساب استفاده می‌شوند.</p>
    </div>

    @if(!$required)
        <div class="mb-5 rounded-2xl border border-blue-200 bg-blue-50 p-4 text-xs leading-7 text-blue-800">
            تکمیل این بخش برای مدیریت عمومی سامانه اختیاری است؛ اما برای استعلام واقعی مشتری، استعلام مستقیم شناسه کالا و ارسال صورتحساب با همین حساب، همه اطلاعات اتصال الزامی هستند.
        </div>
    @endif

    <div class="grid gap-5 md:grid-cols-2">
        <x-form.input name="taxpayer_name" label="نام مودی / شرکت" :value="$profile?->taxpayer_name" :required="$required" data-taxpayer-required />

        <div>
            <label for="taxpayer_type" class="form-label">نوع مودی @if($required)<span class="text-rose-500">*</span>@endif</label>
            <select id="taxpayer_type" name="taxpayer_type" class="form-control" data-taxpayer-required @required($required)>
                <option value="legal" @selected(old('taxpayer_type', $profile?->taxpayer_type ?? 'legal') === 'legal')>حقوقی</option>
                <option value="individual" @selected(old('taxpayer_type', $profile?->taxpayer_type) === 'individual')>حقیقی</option>
            </select>
            @error('taxpayer_type')<p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
        </div>

        <x-form.input name="national_id" label="شناسه ملی / کد ملی" inputmode="numeric" :value="$profile?->national_id" :required="$required" data-taxpayer-required />
        <x-form.input name="economic_code" label="کد اقتصادی" inputmode="numeric" :value="$profile?->economic_code" :required="$required" data-taxpayer-required />
        <x-form.input name="fiscal_id" label="شناسه یکتای حافظه مالیاتی" dir="ltr" maxlength="6" minlength="6" pattern="[A-Za-z0-9]{6}" autocomplete="off" :value="$profile?->fiscal_id" :required="$required" hint="کد ۶ کاراکتری دریافت‌شده از کارپوشه مودیان؛ این کد با کد اقتصادی مشتری متفاوت است." data-taxpayer-required />
        <x-form.input name="branch_code" label="کد شعبه" inputmode="numeric" :value="$profile?->branch_code" hint="در صورت نداشتن شعبه، خالی بگذارید." />

        <div class="md:col-span-2">
            <label for="private_key" class="form-label">کلید خصوصی مودیان @if($required && !$profile)<span class="text-rose-500">*</span>@endif</label>
            <input id="private_key" name="private_key" type="file" accept=".pem,.key,.txt" class="form-control {{ $errors->has('private_key') ? 'form-control-error' : '' }}" @if(!$profile) data-taxpayer-required @endif @required($required && !$profile)>
            <p class="mt-1.5 text-xs text-slate-500">
                فایل PEM، KEY یا TXT حاوی کلید خصوصی بدون رمز، حداکثر ۶۴ کیلوبایت.
                @if($profile) کلید فعلی به‌صورت رمزنگاری‌شده ذخیره شده است؛ برای حفظ آن فایلی انتخاب نکنید. @endif
            </p>
            @error('private_key')<p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
        </div>
    </div>
</section>
