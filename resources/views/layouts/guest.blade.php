<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ورود') | مودیان‌یار</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-900 antialiased">
    <main class="relative grid min-h-screen overflow-hidden lg:grid-cols-[1.05fr_.95fr]">
        <section class="relative hidden overflow-hidden bg-slate-900 p-12 lg:flex lg:flex-col lg:justify-between">
            <div class="guest-glow guest-glow-one"></div>
            <div class="guest-glow guest-glow-two"></div>
            <div class="relative z-10 flex items-center gap-3 text-white">
                <div class="grid size-12 place-items-center rounded-2xl bg-teal-300 text-xl font-black text-slate-950">م</div>
                <div>
                    <div class="text-xl font-extrabold">مودیان‌یار</div>
                    <div class="text-xs text-slate-400">سامانه مدیریت صورتحساب الکترونیکی</div>
                </div>
            </div>
            <div class="relative z-10 max-w-xl">
                <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-teal-300/20 bg-teal-300/10 px-4 py-2 text-sm font-semibold text-teal-200">
                    <span class="size-2 rounded-full bg-teal-300"></span> یک مسیر روشن تا سامانه مودیان
                </div>
                <h1 class="text-5xl font-black leading-[1.35] tracking-tight text-white">صورتحساب‌ها را<br><span class="text-teal-300">دقیق و بدون دغدغه</span><br>مدیریت کنید.</h1>
                <p class="mt-7 max-w-lg text-base leading-8 text-slate-400">از تعریف مشتری و کالا تا ارسال، پیگیری تأیید و ثبت واکنش خریدار؛ همه مراحل در یک فضای یکپارچه.</p>
            </div>
            <div class="relative z-10 grid grid-cols-3 gap-4 text-white">
                <div class="guest-stat"><strong>سریع</strong><span>استعلام کدها</span></div>
                <div class="guest-stat"><strong>شفاف</strong><span>پیگیری وضعیت</span></div>
                <div class="guest-stat"><strong>امن</strong><span>تفکیک دسترسی</span></div>
            </div>
        </section>
        <section class="flex items-center justify-center bg-slate-50 px-5 py-12 sm:px-10">
            <div class="w-full max-w-md">
                <div class="mb-10 flex items-center gap-3 lg:hidden">
                    <div class="grid size-11 place-items-center rounded-2xl bg-slate-900 text-lg font-black text-teal-300">م</div>
                    <div class="text-xl font-extrabold">مودیان‌یار</div>
                </div>
                @yield('content')
            </div>
        </section>
    </main>
</body>
</html>
