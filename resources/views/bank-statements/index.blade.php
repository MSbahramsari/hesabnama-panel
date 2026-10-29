@extends('layouts.app')

@section('title', 'تبدیل صورتحساب بانکی')
@section('page-title', 'تبدیل صورتحساب بانکی')
@section('page-subtitle', 'تبدیل گردش حساب بانک به فایل استاندارد سند حسابداری')

@section('content')
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">ساخت فایل سند حسابداری</h2>
                    <p class="card-subtitle">صورتحساب بانک را بارگذاری کنید؛ هر گردش به دو ردیف متوازن بدهکار و بستانکار تبدیل می‌شود.</p>
                </div>
                <span class="status-badge status-emerald"><span class="size-1.5 rounded-full bg-emerald-500"></span>بدون نگه‌داری فایل بانکی</span>
            </div>

            <form method="POST" action="{{ route('bank-statements.convert') }}" enctype="multipart/form-data" class="space-y-7 p-5 sm:p-7" data-bank-statement-form>
                @csrf

                <div class="rounded-2xl border border-dashed border-teal-300 bg-teal-50/60 p-5 sm:p-6">
                    <label for="statement" class="flex cursor-pointer flex-col items-center justify-center gap-3 text-center">
                        <span class="grid size-12 place-items-center rounded-2xl bg-white text-teal-700 shadow-sm ring-1 ring-teal-100"><x-icon name="bank" class="size-6" /></span>
                        <span class="font-black text-slate-900">فایل صورتحساب بانکی را انتخاب کنید</span>
                        <span class="text-xs leading-6 text-slate-500">فرمت‌های xls و xlsx تا حجم ۳۰ مگابایت</span>
                        <span class="btn-secondary pointer-events-none mt-1" data-bank-file-label>انتخاب فایل</span>
                    </label>
                    <input id="statement" name="statement" type="file" accept=".xls,.xlsx" class="sr-only" required data-bank-file>
                </div>

                <div>
                    <div class="mb-4 flex items-center gap-3">
                        <span class="grid size-8 place-items-center rounded-xl bg-slate-100 text-xs font-black text-slate-700">۱</span>
                        <div><h3 class="text-sm font-black text-slate-900">حساب بانکی در کدینگ حسابداری</h3><p class="mt-1 text-xs text-slate-500">حسابی که مانده بانک در آن ثبت می‌شود.</p></div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label class="form-label" for="bank_moein_code">کد معین حساب بانکی <span class="text-rose-500">*</span></label><input id="bank_moein_code" name="bank_moein_code" value="{{ old('bank_moein_code') }}" class="form-control" dir="ltr" required></div>
                        <div><label class="form-label" for="bank_tafsili_code">کد تفصیلی حساب بانکی</label><input id="bank_tafsili_code" name="bank_tafsili_code" value="{{ old('bank_tafsili_code') }}" class="form-control" dir="ltr"></div>
                    </div>
                </div>

                <div>
                    <div class="mb-4 flex items-center gap-3">
                        <span class="grid size-8 place-items-center rounded-xl bg-slate-100 text-xs font-black text-slate-700">۲</span>
                        <div><h3 class="text-sm font-black text-slate-900">حساب مقابل پیش‌فرض</h3><p class="mt-1 text-xs text-slate-500">برای طرف مقابل همه واریزها و برداشت‌ها استفاده می‌شود و بعداً در نرم‌افزار حسابداری قابل تفکیک است.</p></div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label class="form-label" for="counterpart_moein_code">کد معین حساب مقابل <span class="text-rose-500">*</span></label><input id="counterpart_moein_code" name="counterpart_moein_code" value="{{ old('counterpart_moein_code') }}" class="form-control" dir="ltr" required></div>
                        <div><label class="form-label" for="counterpart_tafsili_code">کد تفصیلی حساب مقابل</label><input id="counterpart_tafsili_code" name="counterpart_tafsili_code" value="{{ old('counterpart_tafsili_code') }}" class="form-control" dir="ltr"></div>
                    </div>
                </div>

                <div>
                    <div class="mb-4 flex items-center gap-3">
                        <span class="grid size-8 place-items-center rounded-xl bg-slate-100 text-xs font-black text-slate-700">۳</span>
                        <div><h3 class="text-sm font-black text-slate-900">تنظیمات ساخت سند</h3><p class="mt-1 text-xs text-slate-500">شماره و نحوه گروه‌بندی اسناد خروجی را مشخص کنید.</p></div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label class="form-label" for="starting_voucher_number">شماره شروع سند <span class="text-rose-500">*</span></label><input id="starting_voucher_number" name="starting_voucher_number" type="number" min="1" value="{{ old('starting_voucher_number', 1) }}" class="form-control" dir="ltr" required></div>
                        <div><label class="form-label" for="grouping">گروه‌بندی خروجی <span class="text-rose-500">*</span></label><select id="grouping" name="grouping" class="form-control" required><option value="daily" @selected(old('grouping', 'daily') === 'daily')>یک سند برای گردش‌های هر روز</option><option value="transaction" @selected(old('grouping') === 'transaction')>یک سند جدا برای هر گردش</option></select></div>
                        <div class="sm:col-span-2"><label class="form-label" for="document_description">توضیحات کل سند</label><input id="document_description" name="document_description" value="{{ old('document_description') }}" class="form-control" placeholder="مثلاً ثبت گردش حساب بانک ملت"></div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <p class="max-w-xl text-xs leading-6 text-slate-500">خروجی با فرمت Excel 97-2003 و مطابق ستون‌ها و مقادیر ثابت فایل راهنمای سند حسابداری ساخته می‌شود.</p>
                    <button type="submit" class="btn-primary min-w-44" data-bank-submit><x-icon name="invoice" class="size-4" /><span>تبدیل و دریافت اکسل</span></button>
                </div>
            </form>
        </section>

        <aside class="space-y-5">
            <section class="card p-5 sm:p-6">
                <div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-2xl bg-blue-50 text-blue-700"><x-icon name="check" class="size-5" /></span><div><h3 class="text-sm font-black">بانک‌های نمونه پشتیبانی‌شده</h3><p class="mt-1 text-xs text-slate-500">شناسایی خودکار ساختار فایل</p></div></div>
                <div class="mt-5 flex flex-wrap gap-2">
                    @foreach(['پارسیان', 'پاسارگاد', 'تجارت', 'رسالت', 'رفاه', 'سپه', 'شهر', 'کشاورزی', 'ملت', 'ملی', 'مهر'] as $bank)
                        <span class="rounded-xl bg-slate-50 px-3 py-2 text-[11px] font-bold text-slate-600 ring-1 ring-inset ring-slate-200">{{ $bank }}</span>
                    @endforeach
                </div>
            </section>

            <section class="rounded-[20px] border border-amber-200 bg-amber-50 p-5 text-amber-950 shadow-sm sm:p-6">
                <div class="flex items-center gap-2 font-black"><x-icon name="warning" class="size-5 text-amber-600" />نکته حسابداری</div>
                <p class="mt-3 text-xs leading-7 text-amber-900/80">حساب مقابل به‌صورت پیش‌فرض روی همه گردش‌ها قرار می‌گیرد. بعد از ورود فایل به نرم‌افزار حسابداری، ردیف‌های حساب مقابل را بر اساس ماهیت هر تراکنش اصلاح کنید.</p>
            </section>
        </aside>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const input = document.querySelector('[data-bank-file]');
            const label = document.querySelector('[data-bank-file-label]');
            const form = document.querySelector('[data-bank-statement-form]');
            const submit = document.querySelector('[data-bank-submit]');

            input?.addEventListener('change', () => {
                label.textContent = input.files?.[0]?.name || 'انتخاب فایل';
            });

            form?.addEventListener('submit', () => {
                submit.disabled = true;
                submit.querySelector('span').textContent = 'در حال پردازش فایل…';

                window.setTimeout(() => {
                    submit.disabled = false;
                    submit.querySelector('span').textContent = 'تبدیل و دریافت اکسل';
                }, 5000);
            });
        });
    </script>
@endsection
