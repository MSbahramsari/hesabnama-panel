<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'داشبورد') | مودیان‌یار</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen text-slate-900 antialiased selection:bg-teal-200 selection:text-teal-950">
    <div class="app-shell" data-app-shell>
        <div class="fixed inset-0 z-30 hidden bg-slate-950/45 backdrop-blur-sm lg:hidden" data-sidebar-backdrop></div>

        <aside class="app-sidebar" data-sidebar>
            <div class="flex h-full flex-col">
                <div class="sidebar-brand">
                    <div class="brand-mark" aria-hidden="true">
                        <span>م</span>
                        <i></i>
                    </div>
                    <div>
                        <div class="text-lg font-black tracking-tight text-white">مودیان‌یار</div>
                        <div class="mt-0.5 text-[11px] font-medium text-slate-400">مدیریت هوشمند امور مالیاتی</div>
                    </div>
                </div>

                <nav class="flex-1 space-y-7 overflow-y-auto px-4 py-6">
                    <div>
                        <div class="nav-label">نمای کلی</div>
                        <a href="{{ route('dashboard') }}" @class(['nav-link', 'active' => request()->routeIs('dashboard')])>
                            <x-icon name="home" class="size-5" />
                            <span>داشبورد</span>
                        </a>
                    </div>

                    <div>
                        <div class="nav-label">عملیات مالیاتی</div>
                        <div class="space-y-1">
                            @if(auth()->user()->hasPermission('customers'))
                                <a href="{{ route('customers.index') }}" @class(['nav-link', 'active' => request()->routeIs('customers.*')])>
                                    <x-icon name="users" class="size-5" /><span>مشتریان</span>
                                </a>
                            @endif
                            @if(auth()->user()->hasPermission('goods'))
                                <a href="{{ route('goods.index') }}" @class(['nav-link', 'active' => request()->routeIs('goods.*')])>
                                    <x-icon name="box" class="size-5" /><span>کالا و خدمات</span>
                                </a>
                            @endif
                            @if(auth()->user()->hasPermission('invoices'))
                                <a href="{{ route('invoices.index') }}" @class(['nav-link', 'active' => request()->routeIs('invoices.*')])>
                                    <x-icon name="invoice" class="size-5" /><span>صورتحساب‌ها</span>
                                    @if(($sidebarPendingCount ?? 0) > 0)
                                        <span class="mr-auto rounded-full bg-amber-300 px-2 py-0.5 text-[10px] font-bold text-slate-900">{{ $sidebarPendingCount }}</span>
                                    @endif
                                </a>
                            @endif
                        </div>
                    </div>

                    @if(auth()->user()->isAdmin())
                        <div>
                            <div class="nav-label">مدیریت سامانه</div>
                            <div class="space-y-1">
                                <a href="{{ route('admin.users.index') }}" @class(['nav-link', 'active' => request()->routeIs('admin.users.*')])>
                                    <x-icon name="settings" class="size-5" /><span>کاربران و مجوزها</span>
                                </a>
                                <a href="{{ route('admin.stuff-catalog.index') }}" @class(['nav-link', 'active' => request()->routeIs('admin.stuff-catalog.*')])>
                                    <x-icon name="box" class="size-5" /><span>بروزرسانی کاتالوگ</span>
                                </a>
                            </div>
                        </div>
                    @endif
                </nav>

                <div class="sidebar-footer">
                    <div class="mb-3 flex items-center justify-between rounded-xl border border-white/8 bg-white/[.035] px-3 py-2.5 text-[11px]">
                        <span class="flex items-center gap-2 text-slate-400"><i class="size-2 rounded-full bg-emerald-400 shadow-[0_0_0_4px_rgba(52,211,153,.08)]"></i>وضعیت سامانه</span>
                        <span class="font-bold text-emerald-300">فعال</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="sidebar-logout">
                            <span class="grid size-9 place-items-center rounded-xl bg-white/5 text-slate-400 ring-1 ring-inset ring-white/8">
                                <x-icon name="logout" class="size-4" />
                            </span>
                            <span class="flex-1 text-right">خروج از حساب</span>
                            <x-icon name="arrow-left" class="size-4 text-slate-600" />
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <main class="app-main min-h-screen lg:mr-72">
            <header class="app-topbar sticky top-0 z-20">
                <div class="mx-auto flex h-[78px] max-w-[1600px] items-center gap-4 px-4 sm:px-6 lg:px-10">
                    <button type="button" class="icon-button lg:hidden" data-sidebar-toggle aria-label="باز کردن منو">
                        <x-icon name="menu" class="size-5" />
                    </button>
                    <div class="min-w-0 flex-1">
                        <div class="mb-0.5 hidden items-center gap-2 text-[10px] font-bold text-slate-400 sm:flex">
                            <span>پنل مدیریت مالیاتی</span><span class="size-1 rounded-full bg-slate-300"></span><span>{{ now()->format('Y/m/d') }}</span>
                        </div>
                        <h1 class="truncate text-lg font-black tracking-tight text-slate-950 sm:text-xl">@yield('page-title', 'داشبورد')</h1>
                        @hasSection('page-subtitle')
                            <p class="mt-1 hidden truncate text-xs text-slate-500 lg:block">@yield('page-subtitle')</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        @yield('page-actions')
                        <a href="{{ route('profile.edit') }}" class="topbar-profile hidden xl:flex">
                            <div class="avatar avatar-light">{{ mb_substr(auth()->user()->name, 0, 1) }}</div>
                            <div class="min-w-0 text-right">
                                <div class="max-w-28 truncate text-xs font-extrabold text-slate-800">{{ auth()->user()->name }}</div>
                                <div class="mt-0.5 text-[10px] text-slate-400">تنظیمات حساب</div>
                            </div>
                        </a>
                        <span class="mx-1 hidden h-7 w-px bg-slate-200 sm:block"></span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="icon-button" title="خروج">
                                <x-icon name="logout" class="size-5" />
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <div class="app-content mx-auto max-w-[1600px] px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
                <x-flash />
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
