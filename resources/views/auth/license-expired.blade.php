@extends('layouts.guest')

@section('title', 'مجوز غیرفعال')

@section('content')
    <div class="text-center">
        <div class="mx-auto grid size-20 place-items-center rounded-3xl bg-amber-100 text-amber-700"><x-icon name="warning" class="size-9" /></div>
        <h2 class="mt-7 text-3xl font-black tracking-tight text-slate-950">مجوز دسترسی فعال نیست</h2>
        <p class="mt-4 text-sm leading-8 text-slate-500">اعتبار حساب شما به پایان رسیده یا توسط مدیر غیرفعال شده است. برای تمدید پلن با مدیر سامانه تماس بگیرید.</p>
        <div class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 text-right shadow-sm">
            <div class="flex items-center justify-between gap-4 py-2 text-sm"><span class="text-slate-500">حساب</span><strong>{{ auth()->user()->email }}</strong></div>
            <div class="flex items-center justify-between gap-4 border-t border-slate-100 py-2 text-sm"><span class="text-slate-500">پایان اعتبار</span><strong>{{ \App\Support\JalaliDate::format(auth()->user()->license_expires_at) ?? 'تعیین نشده' }}</strong></div>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="mt-7">
            @csrf
            <button type="submit" class="btn-secondary w-full justify-center">خروج و ورود با حساب دیگر</button>
        </form>
    </div>
@endsection
