@extends('layouts.app')
@section('title', 'صورتحساب‌ها')
@section('page-title', 'صورتحساب‌ها')
@section('page-subtitle', 'جست‌وجو، ارسال گروهی و پیگیری وضعیت صورتحساب‌ها')
@section('content')
    @if(!$moadianIsReal)
        <div class="mb-5 rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm leading-7 text-blue-900">
            <strong>محیط آزمایشی:</strong> ارسال‌ها شبیه‌سازی می‌شوند و هیچ داده‌ای به سامانه بیرونی فرستاده نمی‌شود.
        </div>
    @elseif(!$moadianIsReady)
        <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-7 text-amber-900">
            <strong>اتصال واقعی هنوز کامل نیست:</strong> کلید خصوصی شناسایی شده، اما شناسه حافظه مالیاتی و شماره اقتصادی فروشنده باید در تنظیمات وارد شوند. تا آن زمان ارسال واقعی انجام نمی‌شود.
        </div>
    @else
        <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm leading-7 text-emerald-900">
            <strong>اتصال مستقیم فعال است:</strong> صورتحساب‌های انتخاب‌شده به سرور رسمی سامانه مودیان ارسال خواهند شد.
        </div>
    @endif

    <div class="card">
        <div class="table-toolbar">
            <form method="GET" class="flex w-full max-w-2xl gap-2">
                @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                @if($type)<input type="hidden" name="type" value="{{ $type }}">@endif
                <div class="relative flex-1">
                    <x-icon name="search" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <input name="q" value="{{ $search }}" class="form-control pr-10" placeholder="شماره داخلی، شماره مالیاتی یا نام مشتری">
                </div>
                <button class="btn-secondary justify-center">جست‌وجو</button>
                @if($search)<a href="{{ route('invoices.index', array_filter(['status' => $status, 'type' => $type])) }}" class="btn-secondary px-3" title="پاک کردن جست‌وجو">×</a>@endif
            </form>
            <div class="flex flex-wrap items-center gap-2">
                <div class="table-count"><span class="size-1.5 rounded-full bg-amber-500"></span><strong>{{ number_format($invoices->total()) }}</strong> صورتحساب</div>
                <a href="{{ route('invoices.export', request()->query()) }}" class="btn-secondary">خروجی اکسل</a>
                <button type="button" class="btn-secondary" data-import-toggle="invoices-import">ورود اکسل</button>
                @if($moadianIsReal)
                    <button type="button" class="btn-secondary" data-import-toggle="moadian-sales-report">همگام‌سازی واکنش خریداران</button>
                @endif
                <a href="{{ route('invoices.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /><span>صورتحساب جدید</span></a>
            </div>
        </div>

        @php($invoiceFilterQuery = request()->except(['page', 'status', 'type']))
        <div class="border-b border-slate-100 bg-slate-50/65 px-4 pt-4 sm:px-6">
            <div class="overflow-x-auto">
                <nav class="flex min-w-max gap-1" aria-label="فیلتر وضعیت صورتحساب">
                    <a href="{{ route('invoices.index', array_filter(array_merge($invoiceFilterQuery, ['type' => $type]))) }}" @class(['rounded-t-xl px-4 py-3 text-xs font-extrabold transition', 'bg-white text-teal-700 shadow-[0_-1px_0_0_#e2e8f0,1px_0_0_0_#e2e8f0,-1px_0_0_0_#e2e8f0]' => $status === '', 'text-slate-500 hover:bg-white/70 hover:text-slate-800' => $status !== ''])>همه وضعیت‌ها</a>
                    @foreach($statuses as $option)
                        <a href="{{ route('invoices.index', array_filter(array_merge($invoiceFilterQuery, ['status' => $option->value, 'type' => $type]))) }}" @class(['rounded-t-xl px-4 py-3 text-xs font-extrabold transition', 'bg-white text-teal-700 shadow-[0_-1px_0_0_#e2e8f0,1px_0_0_0_#e2e8f0,-1px_0_0_0_#e2e8f0]' => $status === $option->value, 'text-slate-500 hover:bg-white/70 hover:text-slate-800' => $status !== $option->value])>{{ $option->label() }}</a>
                    @endforeach
                </nav>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-white px-5 py-3 sm:px-6">
            <span class="ml-1 text-[10px] font-black text-slate-400">نوع صورتحساب:</span>
            <a href="{{ route('invoices.index', array_filter(array_merge($invoiceFilterQuery, ['status' => $status]))) }}" @class(['rounded-full px-3 py-1.5 text-[10px] font-extrabold ring-1 ring-inset transition', 'bg-slate-900 text-white ring-slate-900' => $type === '', 'bg-slate-50 text-slate-500 ring-slate-200 hover:bg-slate-100' => $type !== ''])>همه</a>
            @foreach($types as $option)
                <a href="{{ route('invoices.index', array_filter(array_merge($invoiceFilterQuery, ['status' => $status, 'type' => $option->value]))) }}" @class(['rounded-full px-3 py-1.5 text-[10px] font-extrabold ring-1 ring-inset transition', 'bg-slate-900 text-white ring-slate-900' => $type === $option->value, 'bg-slate-50 text-slate-500 ring-slate-200 hover:bg-slate-100' => $type !== $option->value])>{{ $option->label() }}</a>
            @endforeach
        </div>

        <x-spreadsheet-import-panel id="invoices-import" :action="route('invoices.import')" :template="route('invoices.template')" title="ورود گروهی صورتحساب‌ها" />
        @if($moadianIsReal)
            <x-spreadsheet-import-panel id="moadian-sales-report" :action="route('invoices.moadian-sales-report.import')" title="فایل خروجی فروش داخلی کارپوشه مودیان" />
            <div class="border-b border-slate-100 bg-blue-50/70 px-5 py-3 text-xs leading-6 text-blue-900">
                برای دریافت واکنش واقعی خریدار، فایل xlsx یا csv بخش «مدیریت صورتحساب ← فایل‌های خروجی ← فروش داخلی» را بدون تغییر بارگذاری کنید.
            </div>
        @endif

        @if($invoices->isEmpty())
            <x-empty-state title="صورتحسابی پیدا نشد" description="فیلترها را تغییر دهید یا اولین صورتحساب را بسازید." :action="route('invoices.create')" action-label="ساخت صورتحساب" />
        @else
            <form method="POST" action="{{ route('invoices.send') }}">
                @csrf
                <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <label class="flex items-center gap-2 text-sm font-bold text-slate-600">
                        <input type="checkbox" class="size-4 rounded border-slate-300 text-teal-600" data-select-all-invoices>
                        انتخاب موارد قابل ارسال
                    </label>
                    <button class="btn-primary disabled:cursor-not-allowed disabled:opacity-50" type="submit" @disabled($moadianIsReal && !$moadianIsReady)>
                        <x-icon name="arrow-left" class="size-4" />
                        {{ $moadianIsReal ? 'ارسال واقعی انتخاب‌شده‌ها' : 'ارسال آزمایشی انتخاب‌شده‌ها' }}
                    </button>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th class="w-10"></th><th>صورتحساب</th><th>نوع و تسویه</th><th>مشتری</th><th>تاریخ صدور</th><th>مبلغ نهایی</th><th>وضعیت</th><th class="table-actions-cell">عملیات</th></tr></thead>
                        <tbody>
                        @foreach($invoices as $invoice)
                            @php($canSend = $invoice->user_id === auth()->id() && in_array($invoice->status, [\App\Enums\InvoiceStatus::Draft, \App\Enums\InvoiceStatus::PendingSend, \App\Enums\InvoiceStatus::MoadianError], true))
                            <tr>
                                <td><input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}" @disabled(!$canSend) class="size-4 rounded border-slate-300 text-teal-600 disabled:opacity-30" data-invoice-checkbox></td>
                                <td><a href="{{ route('invoices.show', $invoice) }}" class="table-primary hover:text-teal-700">{{ $invoice->number }}</a><div dir="ltr" class="table-meta justify-end">{{ $invoice->tax_id ?: 'بدون شماره مالیاتی' }}</div></td>
                                <td><x-status-badge :status="$invoice->invoice_type" /><div class="mt-1 text-[10px] font-bold text-slate-400">{{ $invoice->settlement_method->label() }}</div></td>
                                <td><div class="table-primary font-extrabold">{{ $invoice->customer->name }}</div><div dir="ltr" class="table-meta justify-end">{{ $invoice->customer->economic_code }}</div></td>
                                <td dir="ltr" class="table-number text-right">{{ \App\Support\JalaliDate::format($invoice->invoice_date) }}</td>
                                <td class="table-number">{{ number_format($invoice->total) }}<small>ریال</small></td>
                                <td>
                                    <x-status-badge :status="$invoice->moadian_status ?? $invoice->status" />
                                    @if($invoice->buyer_status)
                                        <div class="mt-1"><x-status-badge :status="$invoice->buyer_status" /></div>
                                    @endif
                                </td>
                                <td class="table-actions-cell"><a href="{{ route('invoices.show', $invoice) }}" class="table-action"><x-icon name="eye" />جزئیات</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </form>
            {{ $invoices->onEachSide(1)->links('components.pagination') }}
        @endif
    </div>
@endsection
