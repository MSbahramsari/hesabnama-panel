@props(['profile' => null, 'required' => true, 'showPrintBranding' => false])

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
            <x-form.input name="address" label="نشانی فروشنده" :value="$profile?->address" hint="نشانی کامل شرکت یا مودی که در نسخه چاپی صورتحساب نمایش داده می‌شود." />
        </div>
        <x-form.input name="postal_code" label="کد پستی فروشنده" inputmode="numeric" maxlength="10" :value="$profile?->postal_code" />
        <x-form.input name="phone" label="شماره تماس فروشنده" inputmode="tel" dir="ltr" :value="$profile?->phone" />

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

    @if($showPrintBranding)
    <div class="mt-7 border-t border-slate-100 pt-6">
        <div class="mb-5">
            <h3 class="card-title">هویت نسخه چاپی</h3>
            <p class="card-subtitle">لوگو و تصویر مهر و امضا فقط در نسخه چاپی صورتحساب استفاده می‌شوند.</p>
        </div>
        <div class="grid gap-5 md:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                <label for="company_logo" class="form-label">لوگوی شرکت</label>
                @if($profile?->company_logo_path)
                    <div class="mb-4 flex h-24 items-center justify-center rounded-xl border border-slate-200 bg-white p-3">
                        <img src="{{ route('profile.branding', 'logo') }}" alt="لوگوی فعلی شرکت" class="max-h-full max-w-full object-contain">
                    </div>
                @endif
                <input id="company_logo" name="company_logo" type="file" accept="image/png,image/jpeg,image/webp" class="form-control {{ $errors->has('company_logo') ? 'form-control-error' : '' }}">
                <p class="mt-1.5 text-xs leading-6 text-slate-500">PNG، JPG یا WebP تا ۲ مگابایت؛ لوگوی افقی و پس‌زمینه شفاف نتیجه بهتری دارد.</p>
                @if($profile?->company_logo_path)
                    <label class="mt-3 flex items-center gap-2 text-xs font-bold text-rose-600"><input type="checkbox" name="remove_company_logo" value="1" class="size-4 rounded border-slate-300">حذف لوگوی فعلی</label>
                @endif
                @error('company_logo')<p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                <label for="stamp_signature" class="form-label">تصویر مهر و امضا</label>
                @if($profile?->stamp_signature_path)
                    <div class="mb-4 flex h-24 items-center justify-center rounded-xl border border-slate-200 bg-white p-3">
                        <img src="{{ route('profile.branding', 'stamp-signature') }}" alt="تصویر فعلی مهر و امضا" class="max-h-full max-w-full object-contain">
                    </div>
                @endif
                <input id="stamp_signature" name="stamp_signature" type="file" accept="image/png,image/jpeg,image/webp" class="form-control {{ $errors->has('stamp_signature') ? 'form-control-error' : '' }}">
                <p class="mt-1.5 text-xs leading-6 text-slate-500">PNG، JPG یا WebP تا ۴ مگابایت؛ تصویر بدون حاشیه و با پس‌زمینه سفید یا شفاف بارگذاری شود.</p>
                @if($profile?->stamp_signature_path)
                    <label class="mt-3 flex items-center gap-2 text-xs font-bold text-rose-600"><input type="checkbox" name="remove_stamp_signature" value="1" class="size-4 rounded border-slate-300">حذف تصویر فعلی</label>
                @endif
                @error('stamp_signature')<p class="mt-1.5 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>
    @endif
</section>
