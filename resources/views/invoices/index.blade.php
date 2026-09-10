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
        <div class="invoice-connection-banner" role="status">
            <span class="invoice-connection-icon"><x-icon name="check" class="size-4" /></span>
            <p><strong>اتصال با سامانه مودیان برقرار است:</strong> صورتحساب‌های انتخاب‌شده به سامانه مودیان ارسال خواهند شد.</p>
        </div>
    @endif

    <div class="card">
        <div class="invoice-toolbar">
            <div class="invoice-toolbar-primary">
                <form method="GET" class="invoice-search-form">
                    @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                    @if($type)<input type="hidden" name="type" value="{{ $type }}">@endif
                    @if($buyerStatus)<input type="hidden" name="buyer_status" value="{{ $buyerStatus }}">@endif
                    @if($settlementMethod)<input type="hidden" name="settlement_method" value="{{ $settlementMethod }}">@endif
                    @if($customerId)<input type="hidden" name="customer_id" value="{{ $customerId }}">@endif
                    @if($invoiceDate)<input type="hidden" name="invoice_date" value="{{ $invoiceDate }}">@endif
                    @if($minimumTotal)<input type="hidden" name="minimum_total" value="{{ $minimumTotal }}">@endif
                    <div class="relative min-w-0 flex-1">
                        <x-icon name="search" class="pointer-events-none absolute right-4 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                        <input name="q" value="{{ $search }}" class="form-control pr-11" placeholder="شماره داخلی، شماره مالیاتی یا نام مشتری">
                    </div>
                    <button class="btn-secondary shrink-0 justify-center">جست‌وجو</button>
                    @if($search)<a href="{{ route('invoices.index', array_filter(['status' => $status, 'type' => $type])) }}" class="btn-secondary shrink-0 px-3" title="پاک کردن جست‌وجو">×</a>@endif
                </form>
                <a href="{{ route('invoices.create') }}" class="btn-primary invoice-create-button"><x-icon name="plus" class="size-4" /><span>صورتحساب جدید</span></a>
            </div>

            <div class="invoice-toolbar-secondary">
                <div class="table-count"><span class="size-1.5 rounded-full bg-amber-500"></span><strong>{{ number_format($invoices->total()) }}</strong> صورتحساب</div>
                <div class="invoice-utility-actions">
                    <a href="{{ route('invoices.export', request()->query()) }}" class="btn-secondary">خروجی اکسل</a>
                    <button type="button" class="btn-secondary" data-import-toggle="invoices-import">ورود اکسل</button>
                    @if($moadianIsReal)
                        <button type="button" class="btn-secondary" data-import-toggle="moadian-sales-report">همگام‌سازی واکنش خریداران</button>
                    @endif
                </div>
            </div>
        </div>

        @php($invoiceFilterQuery = request()->except(['page', 'status', 'type']))
        <div class="invoice-filter-panel">
            <div class="invoice-status-filter">
                <span class="invoice-filter-label">وضعیت صورتحساب</span>
                <nav class="invoice-status-tabs" aria-label="فیلتر وضعیت صورتحساب">
                    <a href="{{ route('invoices.index', array_filter(array_merge($invoiceFilterQuery, ['type' => $type]))) }}" @class(['invoice-status-tab', 'active' => $status === ''])>همه وضعیت‌ها</a>
                    @foreach($statuses as $option)
                        <a href="{{ route('invoices.index', array_filter(array_merge($invoiceFilterQuery, ['status' => $option->value, 'type' => $type]))) }}" @class(['invoice-status-tab', 'active' => $status === $option->value])>{{ $option->label() }}</a>
                    @endforeach
                </nav>
            </div>
            <div class="invoice-type-filter">
                <span class="invoice-filter-label">نوع صورتحساب:</span>
                <div class="invoice-type-options">
                    <a href="{{ route('invoices.index', array_filter(array_merge($invoiceFilterQuery, ['status' => $status]))) }}" @class(['invoice-type-option', 'active' => $type === ''])>همه</a>
                    @foreach($types as $option)
                        <a href="{{ route('invoices.index', array_filter(array_merge($invoiceFilterQuery, ['status' => $status, 'type' => $option->value]))) }}" @class(['invoice-type-option', 'active' => $type === $option->value])>{{ $option->label() }}</a>
                    @endforeach
                </div>
            </div>
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
            <form id="invoice-column-filters" method="GET" action="{{ route('invoices.index') }}"></form>
            @foreach($invoices as $invoice)
                <form id="duplicate-invoice-{{ $invoice->id }}" method="POST" action="{{ route('invoices.duplicate', $invoice) }}">
                    @csrf
                </form>
            @endforeach
            <form method="POST" action="{{ route('invoices.send') }}">
                @csrf
                <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/60 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <label class="flex items-center gap-2 text-sm font-bold text-slate-600">
                        <input type="checkbox" class="size-4 rounded border-slate-300 text-teal-600" data-select-all-invoices>
                        انتخاب موارد قابل ارسال
                    </label>
                    <button class="btn-primary disabled:cursor-not-allowed disabled:opacity-50" type="submit" @disabled($moadianIsReal && !$moadianIsReady)>
                        <x-icon name="arrow-left" class="size-4" />
                        ارسال به مودیان
                    </button>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr><th class="w-10"></th><th>صورتحساب</th><th>نوع و تسویه</th><th>مشتری</th><th>تاریخ صدور</th><th>مبلغ نهایی</th><th>وضعیت</th><th class="table-actions-cell">عملیات</th></tr>
                            <tr class="bg-slate-50/80 align-top">
                                <th></th>
                                <th><input form="invoice-column-filters" name="q" value="{{ $search }}" class="form-control min-w-32 !px-3 !py-2 text-xs" placeholder="شماره یا شناسه"></th>
                                <th class="space-y-2">
                                    <select form="invoice-column-filters" name="type" class="form-control min-w-28 !px-2 !py-2 text-xs">
                                        <option value="">همه انواع</option>
                                        @foreach($types as $option)<option value="{{ $option->value }}" @selected($type === $option->value)>{{ $option->label() }}</option>@endforeach
                                    </select>
                                    <select form="invoice-column-filters" name="settlement_method" class="form-control min-w-28 !px-2 !py-2 text-xs">
                                        <option value="">همه تسویه‌ها</option>
                                        @foreach($settlementMethods as $option)<option value="{{ $option->value }}" @selected($settlementMethod === $option->value)>{{ $option->label() }}</option>@endforeach
                                    </select>
                                </th>
                                <th>
                                    <select form="invoice-column-filters" name="customer_id" class="form-control min-w-36 !px-2 !py-2 text-xs">
                                        <option value="">همه مشتریان</option>
                                        @foreach($filterCustomers as $customer)<option value="{{ $customer->id }}" @selected($customerId === $customer->id)>{{ $customer->name }}</option>@endforeach
                                    </select>
                                </th>
                                <th><input form="invoice-column-filters" name="invoice_date" type="date" value="{{ $invoiceDate }}" class="form-control min-w-32 !px-2 !py-2 text-xs"></th>
                                <th><input form="invoice-column-filters" name="minimum_total" type="number" min="0" value="{{ $minimumTotal }}" class="form-control min-w-28 !px-2 !py-2 text-xs" placeholder="حداقل مبلغ"></th>
                                <th class="space-y-2">
                                    <select form="invoice-column-filters" name="status" class="form-control min-w-36 !px-2 !py-2 text-xs">
                                        <option value="">همه وضعیت‌ها</option>
                                        @foreach($statuses as $option)<option value="{{ $option->value }}" @selected($status === $option->value)>{{ $option->label() }}</option>@endforeach
                                    </select>
                                    <select form="invoice-column-filters" name="buyer_status" class="form-control min-w-36 !px-2 !py-2 text-xs">
                                        <option value="">همه واکنش‌ها</option>
                                        <option value="not_synced" @selected($buyerStatus === 'not_synced')>همگام‌سازی نشده</option>
                                        @foreach($buyerStatuses as $option)<option value="{{ $option->value }}" @selected($buyerStatus === $option->value)>{{ $option->label() }}</option>@endforeach
                                    </select>
                                </th>
                                <th class="table-actions-cell">
                                    <div class="flex flex-col gap-2">
                                        <button form="invoice-column-filters" class="table-action justify-center" type="submit">اعمال فیلتر</button>
                                        <a href="{{ route('invoices.index') }}" class="text-center text-[10px] font-bold text-slate-500 hover:text-rose-600">پاک‌کردن</a>
                                    </div>
                                </th>
                            </tr>
                        </thead>
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
                                    <div class="mt-1">
                                        @if($invoice->buyer_status)
                                            <x-status-badge :status="$invoice->buyer_status" />
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-500">همگام‌سازی نشده</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="table-actions-cell">
                                    <div class="table-row-actions">
                                        <a href="{{ route('invoices.show', $invoice) }}" class="table-action"><x-icon name="eye" />جزئیات</a>
                                        <button type="submit" form="duplicate-invoice-{{ $invoice->id }}" class="table-action"><x-icon name="copy" />کپی</button>
                                    </div>
                                </td>
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
