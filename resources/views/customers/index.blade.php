@extends('layouts.app')
@section('title', 'مشتریان')
@section('page-title', 'مشتریان')
@section('page-subtitle', 'اطلاعات خریداران و طرف‌حساب‌ها را مدیریت کنید')
@section('page-actions')<a href="{{ route('customers.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /><span class="hidden sm:inline">مشتری جدید</span></a>@endsection
@section('content')
    <div class="card">
        <div class="table-toolbar">
            <form method="GET" class="flex w-full max-w-lg gap-2">
                <div class="relative flex-1"><x-icon name="search" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" /><input name="q" value="{{ $search }}" class="form-control pr-10" placeholder="نام، شناسه ملی یا کد اقتصادی"></div>
                <button class="btn-secondary">جست‌وجو</button>
                @if($search)<a href="{{ route('customers.index') }}" class="btn-secondary px-3" title="پاک کردن جست‌وجو">×</a>@endif
            </form>
            <div class="table-count"><span class="size-1.5 rounded-full bg-teal-500"></span><strong>{{ number_format($customers->total()) }}</strong> مشتری ثبت‌شده</div>
        </div>
        @if($customers->isEmpty())
            <x-empty-state title="مشتری‌ای پیدا نشد" description="برای شروع، اطلاعات مشتری را با کد اقتصادی استعلام و ذخیره کنید." :action="route('customers.create')" action-label="افزودن مشتری" />
        @else
            <div class="table-wrap"><table class="data-table"><thead><tr><th>مشتری</th><th>کد اقتصادی</th><th>نوع</th><th>تماس</th><th>وضعیت</th><th></th></tr></thead><tbody>
                @foreach($customers as $customer)<tr><td><div class="font-bold text-slate-900">{{ $customer->name }}</div><div class="mt-1 text-xs text-slate-400">{{ $customer->national_id ?: 'بدون شناسه ملی' }}</div></td><td dir="ltr" class="text-right font-mono">{{ $customer->economic_code }}</td><td>{{ $customer->type === 'legal' ? 'حقوقی' : 'حقیقی' }}</td><td dir="ltr" class="text-right">{{ $customer->phone ?: '—' }}</td><td><span @class(['status-badge', 'status-emerald' => $customer->is_active, 'status-slate' => ! $customer->is_active])>{{ $customer->is_active ? 'فعال' : 'غیرفعال' }}</span></td><td><a href="{{ route('customers.edit', $customer) }}" class="table-action">ویرایش</a></td></tr>@endforeach
            </tbody></table></div><div class="border-t border-slate-100 px-5 py-4">{{ $customers->links() }}</div>
        @endif
    </div>
@endsection
