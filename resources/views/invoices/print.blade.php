<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چاپ صورتحساب {{ $invoice->number }}</title>
    <style>
        :root { color-scheme: light; --line: #334155; --soft-line: #94a3b8; --accent: #b9e2ec; --accent-strong: #8ccddd; --muted: #475569; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; background: #eef2f5; color: #0f172a; font-family: Tahoma, Arial, sans-serif; }
        body { padding: 24px; }
        .toolbar { direction: rtl; display: flex; align-items: center; justify-content: space-between; gap: 12px; max-width: 1120px; margin: 0 auto 16px; }
        .toolbar-group { display: flex; gap: 10px; }
        .toolbar-note { color: #64748b; font-size: 12px; }
        .button { border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; color: #1e293b; padding: 10px 16px; font: 700 13px Tahoma, sans-serif; cursor: pointer; text-decoration: none; box-shadow: 0 2px 7px rgba(15, 23, 42, .06); }
        .button-primary { border-color: #0f766e; background: #0f766e; color: #fff; }
        .sheet { width: 1120px; min-height: 760px; margin: 0 auto; padding: 14px; background: #fff; box-shadow: 0 12px 35px rgba(15, 23, 42, .12); }
        .invoice { border: 1.2px solid var(--line); font-size: 10px; line-height: 1.65; }
        .invoice-header { display: grid; grid-template-columns: 29% 42% 29%; min-height: 82px; border-bottom: 1px solid var(--line); }
        .header-cell { padding: 8px 10px; }
        .invoice-title { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
        .invoice-title h1 { margin: 0 0 7px; font-size: 17px; }
        .invoice-title p { margin: 0; color: var(--muted); }
        .logo-wrap { display: flex; align-items: center; justify-content: flex-end; height: 64px; }
        .logo { max-width: 175px; max-height: 62px; object-fit: contain; }
        .brand-fallback { font-size: 16px; font-weight: 800; color: #0f766e; }
        .meta-line { display: grid; grid-template-columns: 105px 1fr; gap: 6px; margin-bottom: 3px; }
        .meta-label { color: var(--muted); }
        .ltr { direction: ltr; unicode-bidi: isolate; text-align: right; }
        .section-title { padding: 3px 8px; border-bottom: 1px solid var(--line); background: var(--accent); color: #164e63; text-align: center; font-weight: 800; }
        .party { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; border-bottom: 1px solid var(--line); }
        .party > div { min-height: 32px; padding: 6px 9px; border-left: 1px solid #cbd5e1; }
        .party > div:last-child { border-left: 0; }
        .party-address { grid-column: span 2; }
        .label { color: var(--muted); margin-left: 5px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border-left: 1px solid var(--line); border-bottom: 1px solid var(--line); padding: 5px 4px; text-align: center; vertical-align: middle; }
        th:last-child, td:last-child { border-left: 0; }
        th { background: #d7eef3; color: #164e63; font-size: 9px; line-height: 1.55; }
        td.description { text-align: right; font-weight: 700; }
        tbody tr { break-inside: avoid; }
        tfoot td { background: var(--accent-strong); font-weight: 800; }
        .payment { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; border-bottom: 1px solid var(--line); }
        .payment > div { min-height: 37px; padding: 7px 8px; border-left: 1px solid var(--line); }
        .payment > div:last-child { border-left: 0; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; min-height: 95px; border-bottom: 1px solid var(--line); }
        .signature-box { position: relative; padding: 7px 10px; border-left: 1px solid var(--line); text-align: center; color: var(--muted); }
        .signature-box:last-child { border-left: 0; }
        .stamp { display: block; max-width: 210px; max-height: 72px; margin: 2px auto 0; object-fit: contain; }
        .notes { display: grid; grid-template-columns: 90px 1fr; min-height: 44px; }
        .notes-label { display: grid; place-items: center; border-left: 1px solid var(--line); color: var(--muted); }
        .notes-text { padding: 7px 10px; }
        .empty { color: #94a3b8; }
        @media (max-width: 1180px) { body { padding: 12px; overflow-x: auto; } }
        @media print {
            @page { size: A4 landscape; margin: 7mm; }
            html, body { width: auto; min-height: auto; background: #fff; }
            body { padding: 0; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .no-print { display: none !important; }
            .sheet { width: 100%; min-height: 0; padding: 0; box-shadow: none; }
            .invoice { font-size: 8.4px; }
            .invoice-header { min-height: 68px; }
            .logo-wrap { height: 52px; }
            .logo { max-height: 50px; }
            .invoice-title h1 { font-size: 15px; }
            th { font-size: 7.8px; }
            th, td { padding: 3px; }
        }
    </style>
</head>
<body>
@php
    $cashAmount = match ($invoice->settlement_method) {
        \App\Enums\SettlementMethod::Cash => (float) $invoice->total,
        \App\Enums\SettlementMethod::Mixed => (float) $invoice->cash_amount,
        default => 0,
    };
    $creditAmount = max(0, (float) $invoice->total - $cashAmount);
@endphp

<div class="toolbar no-print">
    <div class="toolbar-group">
        <button type="button" class="button button-primary" onclick="window.print()">چاپ صورتحساب</button>
        <a class="button" href="{{ route('invoices.show', $invoice) }}">بازگشت به جزئیات</a>
    </div>
    <div class="toolbar-note">برای بهترین نتیجه، جهت کاغذ را روی Landscape و مقیاس را روی Default قرار دهید.</div>
</div>

<main class="sheet">
    <article class="invoice">
        <header class="invoice-header">
            <div class="header-cell">
                <div class="meta-line"><span class="meta-label">شماره منحصر به فرد مالیاتی:</span><strong class="ltr">{{ $invoice->tax_id ?: '—' }}</strong></div>
                <div class="meta-line"><span class="meta-label">تاریخ صدور صورتحساب:</span><strong>{{ \App\Support\JalaliDate::format($invoice->invoice_date) }}</strong></div>
                <div class="meta-line"><span class="meta-label">شماره صورتحساب:</span><strong class="ltr">{{ $invoice->number }}</strong></div>
                <div class="meta-line"><span class="meta-label">نوع صورتحساب:</span><strong>{{ $invoice->invoice_type->label() }}</strong></div>
            </div>
            <div class="header-cell invoice-title">
                <h1>صورتحساب فروش کالا و خدمات</h1>
                <p>صورتحساب الکترونیکی — مبالغ به ریال</p>
                @if($invoice->referenceInvoice)
                    <p>شماره مالیاتی مرجع: <span class="ltr">{{ $invoice->referenceInvoice->tax_id ?: $invoice->referenceInvoice->number }}</span></p>
                @endif
            </div>
            <div class="header-cell logo-wrap">
                @if($profile?->company_logo_path)
                    <img class="logo" src="{{ route('profile.branding', 'logo') }}" alt="لوگوی {{ $profile->taxpayer_name }}">
                @else
                    <div class="brand-fallback">{{ $profile?->taxpayer_name ?? 'واسط نما' }}</div>
                @endif
            </div>
        </header>

        <div class="section-title">مشخصات فروشنده</div>
        <section class="party">
            <div><span class="label">نام شخص حقیقی / حقوقی:</span><strong>{{ $profile?->taxpayer_name ?? $invoice->user->name }}</strong></div>
            <div><span class="label">شماره اقتصادی:</span><strong class="ltr">{{ $profile?->economic_code ?? '—' }}</strong></div>
            <div><span class="label">شناسه ملی:</span><strong class="ltr">{{ $profile?->national_id ?? '—' }}</strong></div>
            <div><span class="label">کد شعبه:</span><strong class="ltr">{{ $profile?->branch_code ?? '—' }}</strong></div>
            <div class="party-address"><span class="label">نشانی:</span><span>{{ $profile?->address ?: '—' }}</span></div>
            <div><span class="label">کد پستی:</span><span class="ltr">{{ $profile?->postal_code ?? '—' }}</span></div>
            <div><span class="label">تلفن:</span><span class="ltr">{{ $profile?->phone ?? '—' }}</span></div>
        </section>

        <div class="section-title">مشخصات خریدار</div>
        <section class="party">
            <div><span class="label">نام شخص حقیقی / حقوقی:</span><strong>{{ $invoice->customer->name }}</strong></div>
            <div><span class="label">شماره اقتصادی:</span><strong class="ltr">{{ $invoice->customer->economic_code ?: '—' }}</strong></div>
            <div><span class="label">شناسه ملی / کد ملی:</span><strong class="ltr">{{ $invoice->customer->national_id ?: '—' }}</strong></div>
            <div><span class="label">نوع شخص:</span><strong>{{ $invoice->customer->type === 'legal' ? 'حقوقی' : 'حقیقی' }}</strong></div>
            <div class="party-address"><span class="label">نشانی:</span><span>{{ $invoice->customer->address ?: '—' }}</span></div>
            <div><span class="label">کد پستی:</span><span class="ltr">{{ $invoice->customer->postal_code ?: '—' }}</span></div>
            <div><span class="label">تلفن:</span><span class="ltr">{{ $invoice->customer->phone ?: '—' }}</span></div>
        </section>

        <table aria-label="اقلام صورتحساب">
            <colgroup>
                <col style="width:3%"><col style="width:10%"><col style="width:22%"><col style="width:6%"><col style="width:6%"><col style="width:9%"><col style="width:9%"><col style="width:7%"><col style="width:9%"><col style="width:5%"><col style="width:6%"><col style="width:8%">
            </colgroup>
            <thead>
            <tr>
                <th>ردیف</th><th>شناسه کالا / خدمت</th><th>شرح کالا / خدمت</th><th>مقدار</th><th>واحد اندازه‌گیری</th><th>مبلغ واحد<br>ریال</th><th>مبلغ قبل از تخفیف<br>ریال</th><th>مبلغ تخفیف<br>ریال</th><th>مبلغ بعد از تخفیف<br>ریال</th><th>نرخ مالیات</th><th>مالیات و عوارض<br>ریال</th><th>مبلغ کل<br>ریال</th>
            </tr>
            </thead>
            <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="ltr">{{ $item->commodity_code }}</td>
                    <td class="description">{{ $item->description }}</td>
                    <td>{{ \App\Support\Decimal::format($item->quantity, 3) }}</td>
                    <td>{{ $item->good?->unit ?? 'عدد' }}</td>
                    <td>{{ number_format((float) $item->unit_price) }}</td>
                    <td>{{ number_format((float) $item->subtotal) }}</td>
                    <td>{{ number_format((float) $item->discount) }}</td>
                    <td>{{ number_format((float) $item->subtotal - (float) $item->discount) }}</td>
                    <td>{{ \App\Support\Decimal::format($item->tax_rate, 2) }}٪</td>
                    <td>{{ number_format((float) $item->tax_amount) }}</td>
                    <td><strong>{{ number_format((float) $item->total) }}</strong></td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr>
                <td colspan="6">جمع کل</td>
                <td>{{ number_format((float) $invoice->subtotal) }}</td>
                <td>{{ number_format((float) $invoice->discount_total) }}</td>
                <td>{{ number_format((float) $invoice->subtotal - (float) $invoice->discount_total) }}</td>
                <td>—</td>
                <td>{{ number_format((float) $invoice->tax_total) }}</td>
                <td>{{ number_format((float) $invoice->total) }}</td>
            </tr>
            </tfoot>
        </table>

        <div class="section-title">اطلاعات پرداخت</div>
        <section class="payment">
            <div><span class="label">روش تسویه:</span><strong>{{ $invoice->settlement_method->label() }}</strong></div>
            <div><span class="label">مبلغ نقدی:</span><strong>{{ number_format($cashAmount) }} ریال</strong></div>
            <div><span class="label">مبلغ نسیه:</span><strong>{{ number_format($creditAmount) }} ریال</strong></div>
            <div><span class="label">مبلغ مالیات:</span><strong>{{ number_format((float) $invoice->tax_total) }} ریال</strong></div>
        </section>

        <section class="signatures">
            <div class="signature-box">مهر و امضای فروشنده
                @if($profile?->stamp_signature_path)
                    <img class="stamp" src="{{ route('profile.branding', 'stamp-signature') }}" alt="مهر و امضای فروشنده">
                @endif
            </div>
            <div class="signature-box">مهر و امضای خریدار</div>
        </section>

        <section class="notes">
            <div class="notes-label">توضیحات</div>
            <div class="notes-text {{ $invoice->description ? '' : 'empty' }}">{{ $invoice->description ?: '—' }}</div>
        </section>
    </article>
</main>
</body>
</html>
