@extends('layouts.app')
@section('title', $invoice->number)
@section('page-title', 'جزئیات صورتحساب')
@section('page-subtitle', $invoice->number.' — '.$invoice->customer->name)
@section('page-actions')
    <a href="{{ route('invoices.index') }}" class="btn-secondary">
        <x-icon name="arrow-left" class="size-4 rotate-180" />
        <span class="hidden sm:inline">بازگشت</span>
    </a>
    @if($invoice->isEditable())
        <a href="{{ route('invoices.edit', $invoice) }}" class="btn-secondary">ویرایش</a>
    @endif
    @can('delete', $invoice)
        <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" data-confirm="این پیش‌نویس صورتحساب حذف شود؟ این عملیات قابل بازگشت نیست.">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-danger">
                <x-icon name="trash" class="size-4" />
                <span class="hidden sm:inline">حذف</span>
            </button>
        </form>
    @endcan
@endsection
@section('content')
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-6">
            <div class="card p-5 sm:p-7">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="text-xs font-bold text-slate-400">شماره صورتحساب</div>
                        <div class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ $invoice->number }}</div>
                        <div class="mt-2 text-sm text-slate-500">تاریخ صدور: {{ $invoice->invoice_date->format('Y/m/d') }}</div>
                    </div>
                    <x-status-badge :status="$invoice->status" />
                </div>
                <div class="mt-7 grid gap-4 border-t border-slate-100 pt-6 sm:grid-cols-2">
                    <div>
                        <div class="text-xs font-bold text-slate-400">خریدار</div>
                        <div class="mt-2 font-extrabold">{{ $invoice->customer->name }}</div>
                        <div dir="ltr" class="mt-1 text-right text-sm text-slate-500">{{ $invoice->customer->economic_code }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-400">شناسه ارسال</div>
                        <div dir="ltr" class="mt-2 break-all text-right font-mono text-xs text-slate-600">{{ $invoice->submission_uid ?? 'هنوز ارسال نشده' }}</div>
                    </div>
                    @if($invoice->tax_id)
                        <div>
                            <div class="text-xs font-bold text-slate-400">شماره منحصر‌به‌فرد مالیاتی</div>
                            <div dir="ltr" class="mt-2 break-all text-right font-mono text-xs text-slate-700">{{ $invoice->tax_id }}</div>
                        </div>
                    @endif
                    @if($invoice->reference_number)
                        <div>
                            <div class="text-xs font-bold text-slate-400">کد رهگیری سامانه مودیان</div>
                            <div dir="ltr" class="mt-2 break-all text-right font-mono text-xs text-slate-700">{{ $invoice->reference_number }}</div>
                        </div>
                    @endif
                </div>
                @if($invoice->description)
                    <div class="mt-5 rounded-2xl bg-slate-50 p-4 text-sm leading-7 text-slate-600">{{ $invoice->description }}</div>
                @endif
                @if($invoice->error_message)
                    <div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm leading-7 text-rose-800">{{ $invoice->error_message }}</div>
                @endif
            </div>

            <div class="card">
                <div class="card-header"><div><h3 class="card-title">اقلام صورتحساب</h3><p class="card-subtitle">{{ number_format($invoice->items->count()) }} ردیف</p></div></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>شرح</th><th>تعداد</th><th>قیمت واحد</th><th>تخفیف</th><th>مالیات</th><th>جمع</th></tr></thead>
                        <tbody>
                        @foreach($invoice->items as $item)
                            <tr>
                                <td><div class="table-primary">{{ $item->description }}</div><div dir="ltr" class="table-meta justify-end">{{ $item->commodity_code }}</div></td>
                                <td class="table-number">{{ number_format($item->quantity, 3) }}</td>
                                <td class="table-number">{{ number_format($item->unit_price) }}</td>
                                <td class="table-number">{{ number_format($item->discount) }}</td>
                                <td class="table-number">{{ number_format($item->tax_amount) }}</td>
                                <td class="table-number text-slate-950">{{ number_format($item->total) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="grid gap-3 border-t border-slate-100 bg-slate-50/60 p-5 sm:mr-auto sm:max-w-sm">
                    <div class="flex justify-between text-sm text-slate-500"><span>جمع اقلام</span><strong>{{ number_format($invoice->subtotal) }}</strong></div>
                    <div class="flex justify-between text-sm text-slate-500"><span>تخفیف</span><strong>{{ number_format($invoice->discount_total) }}</strong></div>
                    <div class="flex justify-between text-sm text-slate-500"><span>مالیات</span><strong>{{ number_format($invoice->tax_total) }}</strong></div>
                    <div class="flex justify-between border-t border-slate-200 pt-3 text-base"><span class="font-bold">مبلغ نهایی</span><strong class="text-lg text-teal-700">{{ number_format($invoice->total) }} ریال</strong></div>
                </div>
            </div>
        </div>

        <aside class="space-y-5">
            <div class="card p-5">
                <h3 class="card-title">چرخه ارسال</h3>
                <div class="mt-5 space-y-0">
                    <div class="timeline-step done"><span>ثبت پیش‌نویس</span><small>{{ $invoice->created_at->format('Y/m/d H:i') }}</small></div>
                    <div @class(['timeline-step', 'done' => $invoice->sent_at, 'current' => !$invoice->sent_at])><span>ارسال به مودیان</span><small>{{ $invoice->sent_at?->format('Y/m/d H:i') ?? 'در انتظار اقدام' }}</small></div>
                    <div @class(['timeline-step', 'done' => $invoice->confirmed_at, 'current' => $invoice->status === \App\Enums\InvoiceStatus::AwaitingConfirmation])><span>تأیید مودیان</span><small>{{ $invoice->confirmed_at?->format('Y/m/d H:i') ?? 'در انتظار پاسخ' }}</small></div>
                </div>

                @can('send', $invoice)
                    @if(!$moadianIsReal || $moadianIsReady)
                        <form method="POST" action="{{ route('invoices.send') }}" class="mt-5">
                            @csrf
                            <input type="hidden" name="invoice_ids[]" value="{{ $invoice->id }}">
                            <button class="btn-primary w-full justify-center">{{ $moadianIsReal ? 'ارسال واقعی به سامانه مودیان' : 'ارسال آزمایشی به مودیان' }}</button>
                        </form>
                    @else
                        <div class="mt-5 rounded-xl bg-amber-50 p-3 text-xs leading-6 text-amber-800">برای ارسال واقعی ابتدا شناسه حافظه مالیاتی و شماره اقتصادی فروشنده را تکمیل کنید.</div>
                    @endif
                @endcan

                @if(!$moadianIsReal)
                    @can('confirm', $invoice)
                        <form method="POST" action="{{ route('invoices.confirm_demo', $invoice) }}" class="mt-3">
                            @csrf
                            <button class="btn-secondary w-full justify-center">شبیه‌سازی پاسخ تأیید</button>
                        </form>
                    @endcan
                @else
                    @can('inquire', $invoice)
                        <form method="POST" action="{{ route('invoices.inquire', $invoice) }}" class="mt-3">
                            @csrf
                            <button class="btn-secondary w-full justify-center">استعلام آخرین وضعیت</button>
                        </form>
                    @endcan
                @endif
            </div>

            @can('updateBuyerStatus', $invoice)
                <div class="card p-5">
                    <h3 class="card-title">واکنش خریدار</h3>
                    <p class="card-subtitle">پس از تأیید مودیان، وضعیت اعلامی خریدار را ثبت کنید.</p>
                    <form method="POST" action="{{ route('invoices.buyer_status', $invoice) }}" class="mt-4 space-y-3">
                        @csrf
                        @method('PATCH')
                        <select name="buyer_status" class="form-control" required>
                            <option value="">انتخاب وضعیت</option>
                            @foreach(\App\Enums\BuyerStatus::cases() as $buyerStatus)
                                <option value="{{ $buyerStatus->value }}" @selected($invoice->buyer_status === $buyerStatus)>{{ $buyerStatus->label() }}</option>
                            @endforeach
                        </select>
                        <button class="btn-primary w-full justify-center">ثبت وضعیت</button>
                    </form>
                </div>
            @endcan

            @if($invoice->buyer_status)
                <div class="card p-5"><div class="text-xs font-bold text-slate-400">آخرین واکنش خریدار</div><div class="mt-3"><x-status-badge :status="$invoice->buyer_status" /></div></div>
            @endif
        </aside>
    </div>
@endsection
