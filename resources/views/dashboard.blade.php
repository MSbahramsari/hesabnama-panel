@extends('layouts.app')

@section('title', 'داشبورد')
@section('page-title', 'داشبورد')
@section('page-subtitle', 'خلاصه‌ای از عملکرد مالیاتی و آخرین فعالیت‌های حساب شما')

@section('page-actions')
    @if(auth()->user()->hasPermission('invoices'))
        <a href="{{ route('invoices.create') }}" class="btn-primary">
            <x-icon name="plus" class="size-4" />
            <span class="hidden sm:inline">صورتحساب جدید</span>
        </a>
    @endif
@endsection

@section('content')
    @php
        $invoiceCount = max((int) $metrics['invoices'], 1);
        $confirmedCount = (int) ($statusCounts['confirmed'] ?? 0);
        $pendingCount = (int) ($statusCounts['pending_send'] ?? 0) + (int) ($statusCounts['awaiting_confirmation'] ?? 0);
        $errorCount = (int) ($statusCounts['moadian_error'] ?? 0);
        $draftCount = (int) ($statusCounts['draft'] ?? 0);
    @endphp

    <section class="dashboard-hero mb-6">
        <div class="absolute -left-20 -top-24 size-72 rounded-full bg-teal-400/15 blur-3xl"></div>
        <div class="absolute -bottom-32 right-1/3 size-80 rounded-full bg-blue-500/10 blur-3xl"></div>
        <div class="relative grid gap-7 p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-center lg:p-9">
            <div>
                <div class="eyebrow"><span class="size-1.5 rounded-full bg-teal-300 shadow-[0_0_0_4px_rgba(94,234,212,.1)]"></span>فضای کاری {{ auth()->user()->plan->label() }}</div>
                <h2 class="mt-5 text-2xl font-black leading-tight sm:text-[32px]">سلام {{ auth()->user()->name }}؛<br><span class="text-slate-400">امروز چه چیزی را مدیریت می‌کنیم؟</span></h2>
                <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-400">ثبت و ارسال صورتحساب، مدیریت طرف‌حساب‌ها و پیگیری پاسخ سامانه مودیان از یک فضای کاری یکپارچه.</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    @if(auth()->user()->hasPermission('invoices'))
                        <a href="{{ route('invoices.index') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-xs font-extrabold text-slate-900 transition hover:-translate-y-0.5 hover:bg-teal-50">
                            مشاهده صورتحساب‌ها <x-icon name="arrow-left" class="size-4" />
                        </a>
                    @endif
                    <a href="{{ route('profile.edit') }}" class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-xs font-bold text-slate-200 transition hover:bg-white/10">
                        تنظیمات پرونده
                    </a>
                </div>
            </div>

            <div class="rounded-[20px] border border-white/10 bg-white/[.055] p-5 backdrop-blur-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-bold text-slate-400">سلامت گردش صورتحساب</div>
                        <div class="mt-2 text-3xl font-black">{{ $metrics['invoices'] > 0 ? number_format(($confirmedCount / $invoiceCount) * 100, 0) : 0 }}<span class="mr-1 text-sm text-teal-300">٪ تأیید</span></div>
                    </div>
                    <div class="grid size-12 place-items-center rounded-2xl bg-emerald-400/10 text-emerald-300 ring-1 ring-inset ring-emerald-300/15"><x-icon name="check" class="size-6" /></div>
                </div>
                <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-gradient-to-l from-teal-300 to-emerald-400" style="width: {{ $metrics['invoices'] > 0 ? ($confirmedCount / $invoiceCount) * 100 : 0 }}%"></div></div>
                <div class="mt-4 grid grid-cols-2 gap-3 border-t border-white/10 pt-4 text-[11px]">
                    <div class="flex items-center justify-between text-slate-400"><span>در حال پیگیری</span><strong class="text-white">{{ number_format($pendingCount) }}</strong></div>
                    <div class="flex items-center justify-between text-slate-400"><span>نیازمند بررسی</span><strong class="text-rose-300">{{ number_format($errorCount) }}</strong></div>
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="metric-card text-blue-600">
            <div><div class="metric-label">مشتریان فعال</div><div class="metric-value">{{ number_format($metrics['customers']) }}</div><div class="metric-caption">طرف‌حساب ثبت‌شده در سامانه</div></div>
            <div class="metric-icon bg-blue-50"><x-icon name="users" class="size-5" /></div>
        </div>
        <div class="metric-card text-violet-600">
            <div><div class="metric-label">کالا و خدمات</div><div class="metric-value">{{ number_format($metrics['goods']) }}</div><div class="metric-caption">قلم آماده استفاده در صورتحساب</div></div>
            <div class="metric-icon bg-violet-50"><x-icon name="box" class="size-5" /></div>
        </div>
        <div class="metric-card text-amber-600">
            <div><div class="metric-label">کل صورتحساب‌ها</div><div class="metric-value">{{ number_format($metrics['invoices']) }}</div><div class="metric-caption">{{ number_format($draftCount) }} پیش‌نویس ذخیره‌شده</div></div>
            <div class="metric-icon bg-amber-50"><x-icon name="invoice" class="size-5" /></div>
        </div>
        <div class="metric-card text-emerald-600">
            <div><div class="metric-label">مبلغ تأییدشده</div><div class="metric-value text-xl">{{ number_format($metrics['confirmed_total']) }}</div><div class="metric-caption">ریال تأییدشده توسط مودیان</div></div>
            <div class="metric-icon bg-emerald-50"><x-icon name="check" class="size-5" /></div>
        </div>
    </section>

    @if($userCount !== null)
        <div class="mt-5 flex flex-col gap-3 rounded-2xl border border-teal-200/80 bg-teal-50/75 px-5 py-4 text-xs text-teal-950 sm:flex-row sm:items-center sm:justify-between">
            <span>در حال حاضر <strong>{{ number_format($userCount) }} کاربر</strong> در فضای کاری شرکت تعریف شده است.</span>
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1 font-extrabold text-teal-800">مدیریت کاربران <x-icon name="arrow-left" class="size-3.5" /></a>
        </div>
    @endif

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(320px,.65fr)]">
        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">آخرین صورتحساب‌ها</h3><p class="card-subtitle">آخرین تغییرات ثبت‌شده در فضای کاری</p></div>
                @if(auth()->user()->hasPermission('invoices'))
                    <a href="{{ route('invoices.index') }}" class="inline-flex items-center gap-1 text-xs font-extrabold text-teal-700 hover:text-teal-900">مشاهده همه <x-icon name="arrow-left" class="size-3.5" /></a>
                @endif
            </div>
            @if($recentInvoices->isEmpty())
                <x-empty-state title="هنوز صورتحسابی ندارید" description="اولین صورتحساب را بسازید تا وضعیت آن را از همین داشبورد دنبال کنید." :action="auth()->user()->hasPermission('invoices') ? route('invoices.create') : null" action-label="ساخت صورتحساب" />
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>صورتحساب</th><th>مشتری</th><th>تاریخ ثبت</th><th>مبلغ نهایی</th><th>وضعیت</th></tr></thead>
                        <tbody>
                            @foreach($recentInvoices as $invoice)
                                <tr class="cursor-pointer" data-navigate="{{ route('invoices.show', $invoice) }}">
                                    <td><span class="table-primary">{{ $invoice->number }}</span></td>
                                    <td><span class="font-bold text-slate-700">{{ $invoice->customer->name }}</span></td>
                                    <td dir="ltr" class="table-number text-right">{{ $invoice->invoice_date->format('Y/m/d') }}</td>
                                    <td class="table-number">{{ number_format($invoice->total) }}<small>ریال</small></td>
                                    <td><x-status-badge :status="$invoice->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card p-5 sm:p-6">
                <div class="mb-5 flex items-center justify-between"><div><h3 class="card-title">وضعیت صورتحساب‌ها</h3><p class="card-subtitle">توزیع وضعیت‌های جاری</p></div><div class="grid size-10 place-items-center rounded-xl bg-slate-100 text-slate-500"><x-icon name="invoice" class="size-5" /></div></div>
                <div class="space-y-5">
                    @foreach([
                        ['label' => 'تأییدشده', 'count' => $confirmedCount, 'color' => 'bg-emerald-500'],
                        ['label' => 'در حال پیگیری', 'count' => $pendingCount, 'color' => 'bg-blue-500'],
                        ['label' => 'پیش‌نویس', 'count' => $draftCount, 'color' => 'bg-slate-400'],
                        ['label' => 'نیازمند بررسی', 'count' => $errorCount, 'color' => 'bg-rose-500'],
                    ] as $statusItem)
                        <div>
                            <div class="mb-2 flex items-center justify-between text-[11px]"><span class="font-bold text-slate-600">{{ $statusItem['label'] }}</span><strong class="text-slate-900">{{ number_format($statusItem['count']) }}</strong></div>
                            <div class="progress-track"><div class="progress-fill {{ $statusItem['color'] }}" style="width: {{ $metrics['invoices'] > 0 ? ($statusItem['count'] / $invoiceCount) * 100 : 0 }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card p-5 sm:p-6">
                <div class="mb-4"><h3 class="card-title">دسترسی سریع</h3><p class="card-subtitle">عملیات پرکاربرد روزانه</p></div>
                <div class="grid gap-2.5">
                    @if(auth()->user()->hasPermission('customers'))
                        <a href="{{ route('customers.create') }}" class="quick-action"><span class="quick-action-icon text-blue-600"><x-icon name="users" class="size-5" /></span><span class="min-w-0 flex-1"><strong class="block text-xs text-slate-800">ثبت مشتری جدید</strong><small class="mt-1 block text-[10px] text-slate-400">افزودن طرف‌حساب</small></span><x-icon name="arrow-left" class="size-4 text-slate-300" /></a>
                    @endif
                    @if(auth()->user()->hasPermission('goods'))
                        <a href="{{ route('goods.create') }}" class="quick-action"><span class="quick-action-icon text-violet-600"><x-icon name="box" class="size-5" /></span><span class="min-w-0 flex-1"><strong class="block text-xs text-slate-800">ثبت کالا یا خدمت</strong><small class="mt-1 block text-[10px] text-slate-400">افزودن قلم جدید</small></span><x-icon name="arrow-left" class="size-4 text-slate-300" /></a>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
