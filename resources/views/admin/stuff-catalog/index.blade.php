@extends('layouts.app')
@section('title', 'بروزرسانی کاتالوگ')
@section('page-title', 'بروزرسانی کاتالوگ کالا و خدمات')
@section('page-subtitle', 'ورود فایل رسمی، مشاهده تغییرات و کنترل زمان آخرین بروزرسانی')
@section('page-actions')
    <a href="{{ route('goods.create') }}" class="btn-secondary hidden sm:inline-flex">مشاهده جست‌وجوی کالا</a>
@endsection
@section('content')
    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="metric-card">
                <div class="metric-icon bg-violet-50 text-violet-700"><x-icon name="box" class="size-6" /></div>
                <div><div class="metric-value">{{ number_format($catalogCount) }}</div><div class="metric-label">کل نسخه‌های کاتالوگ</div></div>
            </div>
            <div class="metric-card">
                <div class="metric-icon bg-blue-50 text-blue-700"><x-icon name="search" class="size-6" /></div>
                <div><div class="metric-value">{{ number_format($uniqueItemCount) }}</div><div class="metric-label">شناسه یکتای کالا و خدمت</div></div>
            </div>
            <div class="metric-card">
                <div class="metric-icon bg-emerald-50 text-emerald-700"><x-icon name="invoice" class="size-6" /></div>
                <div><div class="metric-value">{{ number_format($catalogTypeCount) }}</div><div class="metric-label">نوع شناسه موجود</div></div>
            </div>
            <div class="metric-card">
                <div class="metric-icon bg-amber-50 text-amber-700"><x-icon name="settings" class="size-6" /></div>
                <div>
                    <div class="text-sm font-black leading-7 text-slate-950">{{ $lastSuccessfulImport?->completed_at?->format('Y/m/d H:i') ?? 'هنوز انجام نشده' }}</div>
                    <div class="metric-label">آخرین بروزرسانی موفق</div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-5">
            <div class="card xl:col-span-3">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">ورود فایل رسمی جدید</h3>
                        <p class="card-subtitle">فایل‌های CSV کالا و خدمت را از سامانه رسمی دریافت و بدون تغییر در این بخش بارگذاری کنید.</p>
                    </div>
                    <a href="https://stuffid.tax.gov.ir" target="_blank" rel="noopener noreferrer" class="btn-secondary">دریافت از StuffID</a>
                </div>
                <form method="POST" action="{{ route('admin.stuff-catalog.store') }}" enctype="multipart/form-data" class="space-y-5 p-5 sm:p-7" data-catalog-import-form>
                    @csrf
                    <div>
                        <label for="catalog_files" class="form-label">فایل‌های CSV رسمی</label>
                        <label for="catalog_files" class="flex min-h-44 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/70 px-6 py-8 text-center hover:border-teal-400 hover:bg-teal-50/40">
                            <div class="grid size-12 place-items-center rounded-2xl bg-white text-teal-700 shadow-sm"><x-icon name="invoice" class="size-6" /></div>
                            <strong class="mt-4 text-sm text-slate-800">فایل کالا، خدمت یا هر دو را انتخاب کنید</strong>
                            <span class="mt-2 text-xs leading-6 text-slate-500">حداکثر ۴ فایل CSV یا TXT؛ سقف برنامه برای هر فایل ۵۰۰ مگابایت است.</span>
                            <input id="catalog_files" name="catalog_files[]" type="file" accept=".csv,.txt,text/csv,text/plain" multiple required class="mt-5 block max-w-full text-xs text-slate-600 file:ml-3 file:rounded-lg file:border-0 file:bg-slate-900 file:px-3 file:py-2 file:font-bold file:text-white">
                        </label>
                        @error('catalog_files')<p class="mt-2 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
                        @error('catalog_files.*')<p class="mt-2 text-xs font-bold text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-xs leading-7 text-slate-600">
                        فایل قبلی را پاک نکنید و جدول را خالی نکنید. واردکننده، شناسه‌های جدید را اضافه، موارد تغییرکرده را بروزرسانی و نسخه‌های بدون تغییر را نادیده می‌گیرد.
                    </div>

                    <div class="hidden rounded-2xl border border-teal-200 bg-teal-50 p-4" data-upload-progress aria-live="polite">
                        <div class="flex items-center justify-between gap-4 text-xs font-bold text-teal-900">
                            <span data-upload-status>در حال آماده‌سازی فایل...</span>
                            <span dir="ltr" data-upload-percent>0%</span>
                        </div>
                        <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-teal-100">
                            <div class="h-full w-0 rounded-full bg-teal-600 transition-[width] duration-200" data-upload-progress-bar></div>
                        </div>
                        <p class="mt-3 text-xs leading-6 text-teal-800" data-upload-hint>تا پایان عملیات این صفحه را نبندید.</p>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary" data-upload-submit>شروع بروزرسانی کاتالوگ</button>
                    </div>
                </form>
            </div>

            <div class="space-y-5 xl:col-span-2">
                <div class="rounded-3xl border border-blue-200 bg-blue-50 p-5 sm:p-6">
                    <div class="flex items-start gap-3">
                        <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-blue-600 text-white"><x-icon name="settings" class="size-5" /></div>
                        <div>
                            <h3 class="font-extrabold text-blue-950">مرز اتوماسیون کجاست؟</h3>
                            <p class="mt-2 text-xs leading-7 text-blue-800">
                                بروزرسانی دیتابیس، تشخیص ردیف جدید و تغییرکرده و ثبت گزارش کاملاً خودکار است. فقط دانلود فایل از سامانه رسمی به‌دلیل CAPTCHA نیاز به اقدام مدیر دارد.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 sm:p-6">
                    <h3 class="font-extrabold text-amber-950">پیشنهاد بهره‌برداری</h3>
                    <ol class="mt-3 list-decimal space-y-2 pr-5 text-xs leading-7 text-amber-900">
                        <li>هفته‌ای یک‌بار فایل‌های رسمی را دریافت کنید.</li>
                        <li>هر دو فایل کالا و خدمت را هم‌زمان بارگذاری کنید.</li>
                        <li>گزارش ردیف‌های جدید، تغییرکرده و ردشده را بررسی کنید.</li>
                    </ol>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">تاریخچه بروزرسانی‌ها</h3>
                    <p class="card-subtitle">هر فایل جداگانه ثبت می‌شود تا نتیجه و خطاهای آن قابل پیگیری باشد.</p>
                </div>
            </div>

            @if($imports->isEmpty())
                <div class="px-5 py-14 text-center text-sm font-bold text-slate-500">هنوز فایلی از پنل وارد نشده است.</div>
            @else
                <div class="table-wrap">
                    <table class="data-table min-w-[1050px]">
                        <thead>
                            <tr>
                                <th>فایل</th>
                                <th>مدیر</th>
                                <th>وضعیت</th>
                                <th>جدید</th>
                                <th>بروزشده</th>
                                <th>بدون تغییر</th>
                                <th>ردشده</th>
                                <th>زمان اجرا</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($imports as $import)
                                <tr>
                                    <td>
                                        <div class="max-w-64 truncate font-bold text-slate-900" dir="ltr" title="{{ $import->file_name }}">{{ $import->file_name }}</div>
                                        @if($import->error_message)
                                            <details class="mt-2 max-w-80 text-xs text-rose-700">
                                                <summary class="cursor-pointer font-bold">مشاهده خطا</summary>
                                                <p class="mt-2 whitespace-normal leading-6">{{ $import->error_message }}</p>
                                            </details>
                                        @endif
                                    </td>
                                    <td>{{ $import->user?->name ?? 'حساب حذف‌شده' }}</td>
                                    <td>
                                        <span @class([
                                            'status-badge',
                                            'status-emerald' => $import->status === \App\Models\StuffCatalogImport::STATUS_COMPLETED,
                                            'status-rose' => $import->status === \App\Models\StuffCatalogImport::STATUS_FAILED,
                                            'status-amber' => $import->status === \App\Models\StuffCatalogImport::STATUS_PROCESSING,
                                        ])>
                                            {{ match($import->status) {
                                                \App\Models\StuffCatalogImport::STATUS_COMPLETED => 'موفق',
                                                \App\Models\StuffCatalogImport::STATUS_FAILED => 'ناموفق',
                                                default => 'در حال پردازش',
                                            } }}
                                        </span>
                                    </td>
                                    <td class="font-bold text-emerald-700">{{ number_format($import->new_rows) }}</td>
                                    <td class="font-bold text-blue-700">{{ number_format($import->updated_rows) }}</td>
                                    <td>{{ number_format($import->unchanged_rows) }}</td>
                                    <td class="font-bold text-rose-700">{{ number_format($import->skipped_rows) }}</td>
                                    <td dir="ltr" class="text-right">{{ $import->completed_at?->format('Y/m/d H:i') ?? $import->started_at?->format('Y/m/d H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-5 py-4">{{ $imports->links() }}</div>
            @endif
        </div>
    </div>
@endsection
