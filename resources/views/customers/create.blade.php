@extends('layouts.app')
@section('title', 'مشتری جدید')
@section('page-title', 'افزودن مشتری')
@section('page-subtitle', $isDemo ? 'استعلام آزمایشی یا ثبت دستی اطلاعات خریدار' : 'استعلام مستقیم کد اقتصادی از سامانه مودیان')
@section('page-actions')
    <a href="{{ route('customers.index') }}" class="btn-secondary">
        <x-icon name="arrow-left" class="size-4 rotate-180" />
        <span class="hidden sm:inline">بازگشت</span>
    </a>
@endsection
@section('content')
    <div class="mx-auto max-w-4xl space-y-5">
        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5">
            <div class="flex items-start gap-3">
                <div class="grid size-9 shrink-0 place-items-center rounded-xl bg-blue-600 text-white"><x-icon name="search" class="size-4" /></div>
                <div class="flex-1">
                    <h3 class="font-extrabold text-blue-950">{{ $isDemo ? 'استعلام آزمایشی کد اقتصادی' : 'استعلام کد اقتصادی' }}</h3>
                    @if($isDemo)
                        <p class="mt-1 text-xs leading-6 text-blue-700">برای تست از کد <span dir="ltr" class="font-mono font-bold">411111111111</span> یا <span dir="ltr" class="font-mono font-bold">422222222222</span> استفاده کنید.</p>
                    @else
                        <p class="mt-1 text-xs leading-6 text-blue-700">پس از تکمیل اتصال، اطلاعات مؤدی مستقیماً از سرور رسمی دریافت می‌شود.</p>
                    @endif
                    <form method="GET" class="mt-4 flex flex-col gap-2 sm:flex-row">
                        <input name="economic_code" value="{{ $economicCode }}" class="form-control bg-white" inputmode="numeric" placeholder="کد اقتصادی">
                        <button class="btn-primary shrink-0">استعلام اطلاعات</button>
                    </form>
                    @if($lookupError && $lookupNeedsConfiguration)
                        <div class="mt-4 flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="font-extrabold">تنظیمات اتصال مودیان این حساب کامل نیست.</div>
                                <p class="mt-1 text-xs leading-6 text-amber-700">{{ $lookupError }}</p>
                            </div>
                            <a href="{{ route('profile.edit').'#taxpayer-connection' }}" class="btn-secondary shrink-0">تکمیل تنظیمات اتصال</a>
                        </div>
                    @elseif($lookupError)
                        <p class="mt-3 text-sm font-bold text-rose-600">{{ $lookupError }}</p>
                    @elseif($economicCode && !$lookupResult)
                        <p class="mt-3 text-sm font-bold text-amber-700">اطلاعاتی برای این کد دریافت نشد؛ می‌توانید مشخصات را دستی تکمیل کنید.</p>
                    @endif
                </div>
            </div>
        </div>

        @if(preg_match('/^\d{10,14}$/', $economicCode))
            <form method="POST" action="{{ route('customers.store') }}" class="card p-5 sm:p-7">
                @csrf
                <div class="mb-6">
                    <h3 class="card-title">{{ $lookupResult ? 'اطلاعات استعلام‌شده' : 'ثبت دستی مشتری' }}</h3>
                    <p class="card-subtitle">اطلاعات را پیش از ذخیره بررسی و تکمیل کنید.</p>
                </div>
                <x-customers.form :lookup-result="$lookupResult" :economic-code="$economicCode" />
                <div class="mt-7 flex justify-end gap-3">
                    <a href="{{ route('customers.index') }}" class="btn-secondary">انصراف</a>
                    <button class="btn-primary">ذخیره مشتری</button>
                </div>
            </form>
        @endif
    </div>
@endsection
