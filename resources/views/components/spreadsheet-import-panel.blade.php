@props(['id', 'action', 'template', 'title'])

<section id="{{ $id }}" class="hidden border-b border-teal-100 bg-teal-50/45 px-5 py-5 sm:px-6" data-import-panel>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h3 class="text-sm font-black text-slate-900">{{ $title }}</h3>
            <p class="mt-1 text-xs leading-6 text-slate-500">ابتدا قالب را دریافت کنید، ستون‌ها را تغییر ندهید و فایل xlsx یا csv حداکثر ۲۰ مگابایت بارگذاری کنید.</p>
        </div>
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="flex w-full flex-col gap-2 sm:flex-row lg:max-w-2xl">
            @csrf
            <input type="file" name="spreadsheet" accept=".xlsx,.csv" class="form-control flex-1 bg-white" required>
            <a href="{{ $template }}" class="btn-secondary shrink-0">دریافت قالب</a>
            <button type="submit" class="btn-primary shrink-0">پردازش فایل</button>
        </form>
    </div>
</section>
