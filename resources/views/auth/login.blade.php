@extends('layouts.guest')

@section('title', 'ورود به سامانه')

@section('content')
    <div>
        <div class="mb-8">
            <div class="text-sm font-bold text-teal-700">خوش آمدید</div>
            <h2 class="mt-2 text-3xl font-black tracking-tight text-slate-950">ورود به حساب کاربری</h2>
            <p class="mt-3 text-sm leading-7 text-slate-500">برای مدیریت صورتحساب‌های الکترونیکی، اطلاعات حساب خود را وارد کنید.</p>
        </div>

        @if($errors->any())
            <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-700">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
            @csrf
            <x-form.input name="email" label="ایمیل" type="email" placeholder="name@company.ir" autocomplete="email" required />
            <x-form.input name="password" label="رمز عبور" type="password" placeholder="حداقل ۸ کاراکتر" autocomplete="current-password" required />
            <label class="flex cursor-pointer items-center gap-2.5 text-sm font-semibold text-slate-600">
                <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                ورود من را به خاطر بسپار
            </label>
            <button type="submit" class="btn-primary w-full justify-center py-3.5">ورود به داشبورد <x-icon name="arrow-left" class="size-4" /></button>
        </form>

        <div class="mt-7 rounded-2xl border border-slate-200 bg-white p-4 text-xs leading-6 text-slate-500 shadow-sm">
            <div class="font-bold text-slate-700">حساب آزمایشی</div>
            <div dir="ltr" class="mt-1 text-left font-mono">demo@moadian.test / password</div>
        </div>
    </div>
@endsection
