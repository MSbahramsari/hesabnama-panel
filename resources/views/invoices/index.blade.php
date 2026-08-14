@extends('layouts.app')
@section('title', 'صورتحساب‌ها')
@section('page-title', 'صورتحساب‌ها')
@section('page-subtitle', 'جست‌وجو، ارسال گروهی و پیگیری وضعیت صورتحساب‌ها')
@section('page-actions')
    <a href="{{ route('invoices.create') }}" class="btn-primary">
        <x-icon name="plus" class="size-4" />
        <span class="hidden sm:inline">صورتحساب جدید</span>
    </a>
@endsection
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
            <form method="GET" class="grid w-full gap-2 sm:max-w-3xl sm:grid-cols-[minmax(0,1fr)_190px_auto]">
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <input name="q" value="{{ $search }}" class="form-control pr-10" placeholder="شماره فاکتور یا نام مشتری">
                </div>
                <select name="status" class="form-control">
                    <option value="">همه وضعیت‌ها</option>
                    @foreach($statuses as $option)
                        <option value="{{ $option->value }}" @selected($status === $option->value)>{{ $option->label() }}</option>
                    @endforeach
                </select>
                <button class="btn-secondary justify-center">اعمال فیلتر</button>
            </form>
            <div class="table-count"><span class="size-1.5 rounded-full bg-amber-500"></span><strong>{{ number_format($invoices->total()) }}</strong> صورتحساب</div>
        </div>

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
                        <thead><tr><th class="w-10"></th><th>صورتحساب</th><th>مشتری</th><th>تاریخ صدور</th><th>مبلغ نهایی</th><th>وضعیت</th><th class="table-actions-cell">عملیات</th></tr></thead>
                        <tbody>
                        @foreach($invoices as $invoice)
                            @php($canSend = $invoice->user_id === auth()->id() && in_array($invoice->status, [\App\Enums\InvoiceStatus::Draft, \App\Enums\InvoiceStatus::PendingSend, \App\Enums\InvoiceStatus::MoadianError], true))
                            <tr>
                                <td><input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}" @disabled(!$canSend) class="size-4 rounded border-slate-300 text-teal-600 disabled:opacity-30" data-invoice-checkbox></td>
                                <td><a href="{{ route('invoices.show', $invoice) }}" class="table-primary hover:text-teal-700">{{ $invoice->number }}</a><div class="table-meta">ثبت‌شده در سامانه</div></td>
                                <td><div class="table-primary font-extrabold">{{ $invoice->customer->name }}</div><div dir="ltr" class="table-meta justify-end">{{ $invoice->customer->economic_code }}</div></td>
                                <td dir="ltr" class="table-number text-right">{{ $invoice->invoice_date->format('Y/m/d') }}</td>
                                <td class="table-number">{{ number_format($invoice->total) }}<small>ریال</small></td>
                                <td><x-status-badge :status="$invoice->status" /></td>
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
