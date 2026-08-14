@extends('layouts.app')

@section('title', 'پروفایل')
@section('page-title', 'پروفایل')
@section('page-subtitle', 'اطلاعات حساب، اشتراک و اتصال اختصاصی شما به سامانه مودیان')

@section('content')
    <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[300px_minmax(0,1fr)]">
        <aside class="card p-6">
            <div class="grid size-16 place-items-center rounded-2xl bg-slate-900 text-2xl font-black text-teal-300">{{ mb_substr(auth()->user()->name, 0, 1) }}</div>
            <h2 class="mt-5 text-xl font-black">{{ auth()->user()->name }}</h2>
            <p dir="ltr" class="mt-1 text-right text-sm text-slate-500">{{ auth()->user()->email }}</p>

            <div class="mt-6 space-y-3 border-t border-slate-100 pt-5">
                <div class="flex justify-between text-sm"><span class="text-slate-500">پلن</span><strong>{{ auth()->user()->plan->label() }}</strong></div>
                <div class="flex justify-between text-sm"><span class="text-slate-500">نقش</span><strong>{{ auth()->user()->role->label() }}</strong></div>
                <div class="flex justify-between text-sm"><span class="text-slate-500">اعتبار تا</span><strong>{{ auth()->user()->license_expires_at?->format('Y/m/d') ?? 'بدون انقضا' }}</strong></div>
            </div>

            @if(!auth()->user()->isAdmin() && auth()->user()->taxpayerProfile)
                <div class="mt-6 border-t border-slate-100 pt-5">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-slate-500">اتصال مودیان</span>
                        @if(auth()->user()->taxpayerProfile->connection_verified_at)
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">تأییدشده</span>
                        @else
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">نیازمند بررسی</span>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('profile.moadian.test') }}" class="mt-4">
                        @csrf
                        <button class="btn-secondary w-full">آزمایش اتصال و توکن</button>
                    </form>
                </div>
            @endif
        </aside>

        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="card p-5 sm:p-7">
            @csrf
            @method('PATCH')

            <div class="mb-6">
                <h3 class="card-title">اطلاعات حساب</h3>
                <p class="card-subtitle">برای تغییر رمز، هر دو فیلد رمز را کامل کنید.</p>
            </div>

            <div class="grid gap-5">
                <x-form.input name="name" label="نام" :value="auth()->user()->name" required />
                <x-form.input name="email" label="ایمیل" type="email" :value="auth()->user()->email" required />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="password" label="رمز جدید" type="password" autocomplete="new-password" />
                    <x-form.input name="password_confirmation" label="تکرار رمز جدید" type="password" autocomplete="new-password" />
                </div>
            </div>

            @if(!auth()->user()->isAdmin())
                <x-taxpayer-profile.form :profile="auth()->user()->taxpayerProfile" />
            @endif

            <div class="mt-7 flex justify-end"><button class="btn-primary">ذخیره تغییرات</button></div>
        </form>
    </div>
@endsection
