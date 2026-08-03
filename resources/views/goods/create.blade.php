@extends('layouts.app')
@section('title', 'قلم جدید')
@section('page-title', 'افزودن کالا یا خدمت')
@section('page-subtitle', $isDemo ? 'استعلام آزمایشی یا ثبت دستی قلم' : 'استعلام شناسه کالا و خدمت از سامانه مودیان')
@section('content')
    <div class="mx-auto max-w-4xl space-y-5">
        <div class="rounded-2xl border border-violet-200 bg-violet-50 p-5">
            <div class="flex items-start gap-3">
                <div class="grid size-9 shrink-0 place-items-center rounded-xl bg-violet-600 text-white"><x-icon name="search" class="size-4" /></div>
                <div class="flex-1">
                    <h3 class="font-extrabold text-violet-950">{{ $isDemo ? 'استعلام آزمایشی شناسه کالا' : 'استعلام شناسه کالا یا خدمت' }}</h3>
                    @if($isDemo)
                        <p class="mt-1 text-xs leading-6 text-violet-700">برای تست از کدهای <span dir="ltr" class="font-mono font-bold">10000001</span>، <span dir="ltr" class="font-mono font-bold">10000002</span> یا <span dir="ltr" class="font-mono font-bold">10000003</span> استفاده کنید.</p>
                    @else
                        <p class="mt-1 text-xs leading-6 text-violet-700">شناسه رسمی و کد واحد اندازه‌گیری را قبل از ارسال صورتحساب بررسی کنید.</p>
                    @endif
                    <form method="GET" class="mt-4 flex flex-col gap-2 sm:flex-row">
                        <input name="commodity_code" value="{{ $commodityCode }}" class="form-control bg-white" inputmode="numeric" placeholder="شناسه کالا یا خدمت">
                        <button class="btn-primary shrink-0">استعلام اطلاعات</button>
                    </form>
                    @if($lookupError)
                        <p class="mt-3 text-sm font-bold text-rose-600">{{ $lookupError }}</p>
                    @elseif($commodityCode && !$lookupResult)
                        <p class="mt-3 text-sm font-bold text-amber-700">اطلاعاتی دریافت نشد؛ می‌توانید مشخصات رسمی قلم را دستی تکمیل کنید.</p>
                    @endif
                </div>
            </div>
        </div>

        @if(preg_match('/^\d{8,20}$/', $commodityCode))
            <form method="POST" action="{{ route('goods.store') }}" class="card p-5 sm:p-7">
                @csrf
                <div class="mb-6">
                    <h3 class="card-title">{{ $lookupResult ? 'اطلاعات استعلام‌شده' : 'ثبت دستی قلم' }}</h3>
                    <p class="card-subtitle">شناسه، کد واحد اندازه‌گیری و نرخ مالیات را پیش از ذخیره بررسی کنید.</p>
                </div>
                <x-goods.form :lookup-result="$lookupResult" :commodity-code="$commodityCode" />
                <div class="mt-7 flex justify-end gap-3">
                    <a href="{{ route('goods.index') }}" class="btn-secondary">انصراف</a>
                    <button class="btn-primary">ذخیره قلم</button>
                </div>
            </form>
        @endif
    </div>
@endsection
