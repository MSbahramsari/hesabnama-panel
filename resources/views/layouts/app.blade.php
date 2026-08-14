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
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased selection:bg-teal-200 selection:text-teal-950">
    <div class="app-shell" data-app-shell>
        <div class="fixed inset-0 z-30 hidden bg-slate-950/45 backdrop-blur-sm lg:hidden" data-sidebar-backdrop></div>

        <aside class="app-sidebar" data-sidebar>
            <div class="flex h-full flex-col">
                <div class="flex h-20 items-center gap-3 border-b border-white/10 px-6">
                    <div class="grid size-11 place-items-center rounded-2xl bg-gradient-to-br from-teal-300 to-emerald-400 text-lg font-black text-slate-950 shadow-lg shadow-teal-950/30">م</div>
                    <div>
                        <div class="text-lg font-extrabold tracking-tight text-white">مودیان‌یار</div>
                        <div class="text-xs text-slate-400">دستیار صورتحساب الکترونیکی</div>
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

                <div class="border-t border-white/10 p-4">
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-2xl p-2.5 transition hover:bg-white/7">
                        <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-white/10 text-sm font-extrabold text-teal-200">{{ mb_substr(auth()->user()->name, 0, 1) }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-bold text-white">{{ auth()->user()->name }}</div>
                            <div class="truncate text-xs text-slate-400">{{ auth()->user()->plan->label() }}</div>
                        </div>
                        <x-icon name="arrow-left" class="size-4 text-slate-500" />
                    </a>
                </div>
            </div>
        </aside>

        <main class="min-h-screen lg:mr-72">
            <header class="app-topbar sticky top-0 z-20">
                <div class="mx-auto flex h-[76px] max-w-[1680px] items-center gap-4 px-4 sm:px-6 lg:px-9">
                    <button type="button" class="icon-button lg:hidden" data-sidebar-toggle aria-label="باز کردن منو">
                        <x-icon name="menu" class="size-5" />
                    </button>
                    <div class="min-w-0 flex-1">
                        <h1 class="truncate text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">@yield('page-title', 'داشبورد')</h1>
                        @hasSection('page-subtitle')
                            <p class="mt-1 hidden truncate text-sm text-slate-500 sm:block">@yield('page-subtitle')</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        @yield('page-actions')
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="icon-button" title="خروج">
                                <x-icon name="logout" class="size-5" />
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <div class="app-content mx-auto max-w-[1680px] px-4 py-6 sm:px-6 lg:px-9 lg:py-8">
                <x-flash />
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
