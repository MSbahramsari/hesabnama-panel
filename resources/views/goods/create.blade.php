@extends('layouts.app')
@section('title', 'جست‌وجو و افزودن کالا یا خدمت')
@section('page-title', 'کاتالوگ کالا و خدمات')
@section('page-subtitle', 'جست‌وجوی نام یا شناسه، بررسی نرخ ارزش افزوده و افزودن مستقیم به اقلام شما')
@section('content')
    <div class="space-y-5">
        <div class="card">
            <div class="border-b border-slate-100 bg-gradient-to-l from-violet-50 to-white px-5 py-6 sm:px-7">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="grid size-11 shrink-0 place-items-center rounded-2xl bg-violet-600 text-white shadow-lg shadow-violet-200">
                            <x-icon name="search" class="size-5" />
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-violet-950">دریافت شناسه کالا و خدمت</h3>
                            <p class="mt-1 max-w-2xl text-xs leading-6 text-violet-700">
                                نام یا بخشی از شرح کالا و خدمت را بنویسید؛ سپس نتیجه صحیح را با توجه به نوع، نرخ مالیات و بازه اعتبار انتخاب کنید.
                            </p>
                        </div>
                    </div>
                    <a href="https://stuffid.tax.gov.ir" target="_blank" rel="noopener noreferrer" class="btn-secondary shrink-0">
                        مرجع رسمی سازمان مالیاتی
                    </a>
                </div>
            </div>

            <form method="GET" action="{{ route('goods.create') }}" class="grid gap-4 p-5 sm:p-7 lg:grid-cols-12">
                <div class="lg:col-span-6">
                    <label for="catalog_query" class="form-label">نام یا شناسه کالا / خدمت</label>
                    <input id="catalog_query" name="catalog_query" value="{{ $catalogSearch }}" class="form-control" placeholder="مثلاً خدمات حسابداری یا شناسه ۱۳ رقمی" autofocus>
                </div>
                <div class="lg:col-span-3">
                    <label for="catalog_type" class="form-label">نوع شناسه</label>
                    <select id="catalog_type" name="catalog_type" class="form-control">
                        <option value="">همه انواع</option>
                        @foreach($catalogTypes as $type)
                            <option value="{{ $type }}" @selected($catalogType === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <label for="catalog_vat" class="form-label">ارزش افزوده</label>
                    <select id="catalog_vat" name="catalog_vat" class="form-control">
                        <option value="">همه نرخ‌ها</option>
                        @foreach($catalogVats as $vat)
                            <option value="{{ $vat }}" @selected($catalogVat !== '' && (float) $catalogVat === (float) $vat)>{{ number_format((float) $vat, 2) }}٪</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end lg:col-span-1">
                    <button class="btn-primary w-full justify-center">جست‌وجو</button>
                </div>
            </form>

            @if($catalogCount === 0)
                <div class="mx-5 mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-7 text-amber-800 sm:mx-7 sm:mb-7">
                    کاتالوگ رسمی هنوز روی سرور وارد نشده است. مدیر سیستم باید فایل CSV دریافت‌شده از سامانه رسمی را با دستور واردسازی کاتالوگ بارگذاری کند.
                </div>
            @elseif($catalogResults)
                <div class="border-t border-slate-100">
                    <div class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                        <div>
                            <h4 class="font-extrabold text-slate-900">نتایج جست‌وجو</h4>
                            <p class="mt-1 text-xs text-slate-500">{{ number_format($catalogResults->total()) }} نتیجه از {{ number_format($catalogCount) }} ردیف کاتالوگ</p>
                        </div>
                        <span class="text-xs font-semibold text-slate-400">برای نرخ‌های متغیر، بازه اجرای شناسه را بررسی کنید.</span>
                    </div>

                    @if($catalogResults->isEmpty())
                        <div class="border-t border-slate-100 px-5 py-12 text-center text-sm font-bold text-slate-500">
                            نتیجه‌ای با این مشخصات پیدا نشد.
                        </div>
                    @else
                        <div class="table-wrap border-t border-slate-100">
                            <table class="data-table min-w-[1050px]">
                                <thead>
                                    <tr>
                                        <th>شناسه</th>
                                        <th>نام کالا یا خدمت</th>
                                        <th>نوع</th>
                                        <th>ارزش افزوده</th>
                                        <th>تاریخ اجرا</th>
                                        <th>تاریخ انقضا</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($catalogResults as $item)
                                        <tr>
                                            <td dir="ltr" class="text-right font-mono font-bold text-slate-800">{{ $item->item_id }}</td>
                                            <td class="min-w-80 font-bold leading-7 text-slate-900">{{ $item->description }}</td>
                                            <td><span class="status-badge status-slate">{{ $item->type ?: 'نامشخص' }}</span></td>
                                            <td><span @class(['status-badge', 'status-emerald' => (float) $item->vat === 0.0, 'status-amber' => (float) $item->vat > 0])>{{ number_format((float) $item->vat, 2) }}٪</span></td>
                                            <td dir="ltr" class="text-right">{{ $item->effective_date ?: '—' }}</td>
                                            <td dir="ltr" class="text-right">{{ $item->expiration_date ?: '—' }}</td>
                                            <td>
                                                <a href="{{ route('goods.create', array_merge(request()->except(['commodity_code', 'catalog_item']), ['catalog_item' => $item->id])) }}" class="btn-secondary whitespace-nowrap px-3 py-2 text-xs">
                                                    انتخاب و افزودن
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-slate-100 px-5 py-4 sm:px-7">{{ $catalogResults->links() }}</div>
                    @endif
                </div>
            @endif
        </div>

        <details class="card group" @if($commodityCode && !$selectedCatalogItem) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-5 sm:px-7">
                <div>
                    <h3 class="card-title">شناسه را از قبل دارم</h3>
                    <p class="card-subtitle">شناسه دقیق را برای بررسی در کاتالوگ محلی یا درگاه مودیان وارد کنید.</p>
                </div>
                <span class="text-xl text-slate-400 transition group-open:rotate-180">⌄</span>
            </summary>
            <form method="GET" action="{{ route('goods.create') }}" class="flex flex-col gap-3 border-t border-slate-100 p-5 sm:flex-row sm:p-7">
                <input name="commodity_code" value="{{ $commodityCode }}" class="form-control" inputmode="numeric" placeholder="شناسه ۱۳ رقمی کالا یا خدمت">
                <button class="btn-primary shrink-0 justify-center">بررسی شناسه</button>
            </form>
            @if($lookupError)
                <p class="mx-5 mb-5 rounded-xl bg-rose-50 p-3 text-sm font-bold text-rose-700 sm:mx-7 sm:mb-7">{{ $lookupError }}</p>
            @elseif($commodityCode && !$lookupResult)
                <p class="mx-5 mb-5 rounded-xl bg-amber-50 p-3 text-sm font-bold text-amber-700 sm:mx-7 sm:mb-7">اطلاعاتی برای این شناسه دریافت نشد؛ می‌توانید مشخصات قلم را دستی تکمیل کنید.</p>
            @endif
        </details>

        @if(preg_match('/^\d{8,20}$/', $commodityCode))
            <form method="POST" action="{{ route('goods.store') }}" class="card p-5 sm:p-7">
                @csrf
                <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="card-title">{{ $lookupResult ? 'اطلاعات قلم انتخاب‌شده' : 'ثبت دستی قلم' }}</h3>
                        <p class="card-subtitle">اطلاعات و نرخ مالیات را بررسی کنید، قیمت واحد را وارد کنید و سپس قلم را ذخیره کنید.</p>
                    </div>
                    @if($selectedCatalogItem)
                        <div class="rounded-xl bg-emerald-50 px-3 py-2 text-xs font-bold leading-6 text-emerald-800">
                            {{ $selectedCatalogItem->type ?: 'کاتالوگ رسمی' }}
                            @if($selectedCatalogItem->effective_date)
                                · اجرا از {{ $selectedCatalogItem->effective_date }}
                            @endif
                        </div>
                    @endif
                </div>
                <x-goods.form :lookup-result="$lookupResult" :commodity-code="$commodityCode" />
                <div class="mt-7 flex justify-end gap-3">
                    <a href="{{ route('goods.index') }}" class="btn-secondary">انصراف</a>
                    <button class="btn-primary">ذخیره در کالا و خدمات من</button>
                </div>
            </form>
        @endif
    </div>
@endsection
