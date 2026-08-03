@extends('layouts.app')

@section('title', 'داشبورد')
@section('page-title', 'داشبورد')
@section('page-subtitle', 'نمایی سریع از وضعیت فعالیت و صورتحساب‌های شما')

@section('page-actions')
    @if(auth()->user()->hasPermission('invoices'))
        <a href="{{ route('invoices.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /><span class="hidden sm:inline">صورتحساب جدید</span></a>
    @endif
@endsection

@section('content')
    <section class="relative mb-7 overflow-hidden rounded-3xl bg-slate-900 p-6 text-white shadow-xl shadow-slate-200 sm:p-8">
        <div class="absolute -left-16 -top-20 size-64 rounded-full bg-teal-400/15 blur-3xl"></div>
        <div class="absolute -bottom-24 right-1/3 size-64 rounded-full bg-blue-500/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold text-teal-200"><span class="size-2 rounded-full bg-teal-300"></span>مجوز {{ auth()->user()->plan->label() }}</div>
                <h2 class="text-2xl font-black sm:text-3xl">سلام {{ auth()->user()->name }}، آماده‌ای؟</h2>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-300">صورتحساب‌ها را آماده کنید، وضعیت ارسال را ببینید و پاسخ خریداران را در یک مسیر شفاف مدیریت کنید.</p>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:flex">
                <div class="hero-mini-stat"><strong>{{ number_format($statusCounts['awaiting_confirmation'] ?? 0) }}</strong><span>منتظر تأیید</span></div>
                <div class="hero-mini-stat"><strong>{{ number_format($statusCounts['moadian_error'] ?? 0) }}</strong><span>نیازمند بررسی</span></div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="metric-card"><div class="metric-icon bg-blue-50 text-blue-600"><x-icon name="users" class="size-6" /></div><div><div class="metric-value">{{ number_format($metrics['customers']) }}</div><div class="metric-label">مشتری ثبت‌شده</div></div></div>
        <div class="metric-card"><div class="metric-icon bg-violet-50 text-violet-600"><x-icon name="box" class="size-6" /></div><div><div class="metric-value">{{ number_format($metrics['goods']) }}</div><div class="metric-label">کالا و خدمت</div></div></div>
        <div class="metric-card"><div class="metric-icon bg-amber-50 text-amber-600"><x-icon name="invoice" class="size-6" /></div><div><div class="metric-value">{{ number_format($metrics['invoices']) }}</div><div class="metric-label">کل صورتحساب‌ها</div></div></div>
        <div class="metric-card"><div class="metric-icon bg-emerald-50 text-emerald-600"><x-icon name="check" class="size-6" /></div><div><div class="metric-value text-xl">{{ number_format($metrics['confirmed_total']) }}</div><div class="metric-label">مبلغ تأییدشده (ریال)</div></div></div>
    </section>

    @if($userCount !== null)
        <div class="mt-6 rounded-2xl border border-teal-200 bg-teal-50 px-5 py-4 text-sm text-teal-900">در حال حاضر <strong>{{ number_format($userCount) }} کاربر</strong> در سامانه تعریف شده است. <a href="{{ route('admin.users.index') }}" class="font-bold underline underline-offset-4">مدیریت کاربران</a></div>
    @endif

    <section class="mt-7 card">
        <div class="card-header">
            <div><h3 class="card-title">آخرین صورتحساب‌ها</h3><p class="card-subtitle">تازه‌ترین فعالیت‌های ثبت‌شده</p></div>
            @if(auth()->user()->hasPermission('invoices'))<a href="{{ route('invoices.index') }}" class="text-sm font-bold text-teal-700 hover:text-teal-800">مشاهده همه</a>@endif
        </div>
        @if($recentInvoices->isEmpty())
            <x-empty-state title="هنوز صورتحسابی ندارید" description="اولین صورتحساب را بسازید تا وضعیت آن را از همین داشبورد دنبال کنید." :action="auth()->user()->hasPermission('invoices') ? route('invoices.create') : null" action-label="ساخت صورتحساب" />
        @else
            <div class="table-wrap"><table class="data-table"><thead><tr><th>شماره</th><th>مشتری</th><th>تاریخ</th><th>مبلغ</th><th>وضعیت</th></tr></thead><tbody>
                @foreach($recentInvoices as $invoice)
                    <tr class="cursor-pointer" data-navigate="{{ route('invoices.show', $invoice) }}"><td class="font-bold text-slate-900">{{ $invoice->number }}</td><td>{{ $invoice->customer->name }}</td><td>{{ $invoice->invoice_date->format('Y/m/d') }}</td><td>{{ number_format($invoice->total) }} ریال</td><td><x-status-badge :status="$invoice->status" /></td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>
@endsection
