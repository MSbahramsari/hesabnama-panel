@extends('layouts.app')
@section('title', 'جست‌وجو و افزودن کالا یا خدمت')
@section('page-title', 'کاتالوگ کالا و خدمات')
@section('page-subtitle', 'جست‌وجوی نام یا شناسه، بررسی نرخ ارزش افزوده و افزودن مستقیم به اقلام شما')
@section('page-actions')
    <a href="{{ route('goods.index') }}" class="btn-secondary">
        <x-icon name="arrow-left" class="size-4 rotate-180" />
        <span class="hidden sm:inline">بازگشت</span>
    </a>
@endsection
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
                    <input id="catalog_query" name="catalog_query" value="{{ $catalogSearch }}" class="form-control" placeholder="مثلاً خدمات حسابداری یا شناسه ۱۳ رقمی" @if(!$selectedCatalogItem) autofocus @endif>
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
                    <div class="flex flex-col gap-3 bg-slate-50/40 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                        <div>
                            <h4 class="font-extrabold text-slate-900">نتایج جست‌وجو</h4>
                            <p class="mt-1 text-xs text-slate-500">نمایش {{ number_format($catalogResults->count()) }} نتیجه از {{ number_format($catalogCount) }} ردیف کاتالوگ</p>
                        </div>
                        <span class="inline-flex w-fit items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[10px] font-bold text-amber-800"><x-icon name="warning" class="size-4" />برای نرخ‌های متغیر، بازه اجرای شناسه را بررسی کنید.</span>
                    </div>

                    @if($catalogResults->isEmpty())
                        <div class="border-t border-slate-100 px-5 py-12 text-center text-sm font-bold text-slate-500">
                            نتیجه‌ای با این مشخصات پیدا نشد.
                        </div>
                    @else
                        <div class="table-wrap catalog-table-wrap border-t border-slate-100">
                            <table class="data-table catalog-table">
                                <colgroup>
                                    <col class="w-[15%]">
                                    <col class="w-[31%]">
                                    <col class="w-[14%]">
                                    <col class="w-[13%]">
                                    <col class="w-[15%]">
                                    <col class="w-[12%]">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>شناسه</th>
                                        <th>نام کالا یا خدمت</th>
                                        <th>نوع</th>
                                        <th>مالیات</th>
                                        <th>بازه اعتبار</th>
                                        <th class="table-actions-cell">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($catalogResults as $item)
                                        <tr>
                                            <td data-label="شناسه" dir="ltr" class="text-right"><span class="catalog-id">{{ $item->item_id }}</span></td>
                                            <td data-label="نام کالا یا خدمت"><div class="catalog-description">{{ $item->description }}</div></td>
                                            <td data-label="نوع"><span class="catalog-chip">{{ $item->type ?: 'نامشخص' }}</span></td>
                                            <td data-label="مالیات">
                                                <div class="catalog-tax-cell">
                                                    <span @class(['catalog-vat', 'catalog-vat-exempt' => (float) $item->vat === 0.0, 'catalog-vat-taxable' => (float) $item->vat > 0])>{{ number_format((float) $item->vat, 2) }}٪</span>
                                                    <span class="catalog-tax-state">{{ $item->taxable ?: ((float) $item->vat > 0 ? 'مشمول' : 'معاف') }}</span>
                                                </div>
                                            </td>
                                            <td data-label="بازه اعتبار">
                                                <div class="catalog-validity">
                                                    <div><span>از</span><time dir="ltr" class="catalog-date">{{ $item->effective_date ?: '—' }}</time></div>
                                                    <div><span>تا</span><time dir="ltr" class="catalog-date">{{ $item->expiration_date ?: 'بدون انقضا' }}</time></div>
                                                </div>
                                            </td>
                                            <td data-label="عملیات" class="table-actions-cell">
                                                <a href="{{ route('goods.create', array_merge(request()->except(['commodity_code', 'catalog_item']), ['catalog_item' => $item->id])).'#good-details' }}" class="table-action catalog-select-action">
                                                    <x-icon name="plus" class="size-3.5" />
                                                    انتخاب و تکمیل
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        {{ $catalogResults->onEachSide(1)->links('components.pagination') }}
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
            @if($lookupError && $lookupNeedsConfiguration)
                <div class="mx-5 mb-5 flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:mx-7 sm:mb-7 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="font-extrabold">تنظیمات اتصال مودیان این حساب کامل نیست.</div>
                        <p class="mt-1 text-xs leading-6 text-amber-700">{{ $lookupError }}</p>
                    </div>
                    <a href="{{ route('profile.edit').'#taxpayer-connection' }}" class="btn-secondary shrink-0">تکمیل تنظیمات اتصال</a>
                </div>
            @elseif($lookupError)
                <p class="mx-5 mb-5 rounded-xl bg-rose-50 p-3 text-sm font-bold text-rose-700 sm:mx-7 sm:mb-7">{{ $lookupError }}</p>
            @elseif($commodityCode && !$lookupResult)
                <p class="mx-5 mb-5 rounded-xl bg-amber-50 p-3 text-sm font-bold text-amber-700 sm:mx-7 sm:mb-7">اطلاعاتی برای این شناسه دریافت نشد؛ می‌توانید مشخصات قلم را دستی تکمیل کنید.</p>
            @endif
        </details>

        @if(preg_match('/^\d{8,20}$/', $commodityCode))
            <form id="good-details" method="POST" action="{{ route('goods.store') }}" class="card scroll-mt-24 p-5 sm:p-7" @if($selectedCatalogItem) data-selected-catalog-form @endif>
                @csrf
                @if($selectedCatalogItem)
                    <div class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                        <span class="grid size-8 shrink-0 place-items-center rounded-xl bg-emerald-600 text-white"><x-icon name="check" class="size-4" /></span>
                        <div>
                            <div class="font-extrabold">قلم از کاتالوگ رسمی انتخاب شد.</div>
                            <p class="mt-1 text-xs leading-6 text-emerald-700">اطلاعات استعلام‌شده را بررسی کنید، قیمت واحد را وارد کنید و برای افزودن به کالاهای خود دکمه ذخیره را بزنید.</p>
                        </div>
                    </div>
                @endif
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
