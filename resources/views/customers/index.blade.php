@extends('layouts.app')

@section('title', 'مشتریان')
@section('page-title', 'مشتریان')
@section('page-subtitle', 'اطلاعات خریداران و طرف‌حساب‌ها را مدیریت کنید')

@section('content')
    <div class="card">
        <div class="table-toolbar">
            <form method="GET" class="flex w-full max-w-lg gap-2">
                <div class="relative flex-1">
                    <x-icon name="search" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <input name="q" value="{{ $search }}" class="form-control pr-10" placeholder="نام، شناسه ملی یا کد اقتصادی">
                </div>
                <button class="btn-secondary">جست‌وجو</button>
                @if($search)<a href="{{ route('customers.index') }}" class="btn-secondary px-3" title="پاک کردن جست‌وجو">×</a>@endif
            </form>
            <div class="flex items-center gap-2">
                <div class="table-count"><span class="size-1.5 rounded-full bg-teal-500"></span><strong>{{ number_format($customers->total()) }}</strong> مشتری ثبت‌شده</div>
                <a href="{{ route('customers.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /><span>مشتری جدید</span></a>
            </div>
        </div>

        @if($customers->isEmpty())
            <x-empty-state title="مشتری‌ای پیدا نشد" description="برای شروع، اطلاعات مشتری را با کد اقتصادی استعلام و ذخیره کنید." :action="route('customers.create')" action-label="افزودن مشتری" />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>مشتری</th><th>کد اقتصادی</th><th>نوع شخصیت</th><th>شماره تماس</th><th>وضعیت</th><th class="table-actions-cell">عملیات</th></tr></thead>
                    <tbody>
                        @foreach($customers as $customer)
                            <tr>
                                <td><div class="table-primary">{{ $customer->name }}</div><div class="table-meta">{{ $customer->national_id ?: 'بدون شناسه ملی' }}</div></td>
                                <td dir="ltr" class="text-right"><span class="table-code">{{ $customer->economic_code }}</span></td>
                                <td><span class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-[10px] font-bold text-slate-600">{{ $customer->type === 'legal' ? 'حقوقی' : 'حقیقی' }}</span></td>
                                <td dir="ltr" class="table-number text-right">{{ $customer->phone ?: '—' }}</td>
                                <td><span @class(['status-badge', 'status-emerald' => $customer->is_active, 'status-slate' => ! $customer->is_active])>{{ $customer->is_active ? 'فعال' : 'غیرفعال' }}</span></td>
                                <td class="table-actions-cell">
                                    <div class="table-row-actions">
                                        <a href="{{ route('customers.edit', $customer) }}" class="table-action"><x-icon name="edit" />ویرایش</a>
                                        @can('delete', $customer)
                                            <form method="POST" action="{{ route('customers.destroy', $customer) }}" data-confirm="این مشتری حذف شود؟ این عملیات قابل بازگشت نیست.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="table-action table-action-danger"><x-icon name="trash" />حذف</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $customers->onEachSide(1)->links('components.pagination') }}
        @endif
    </div>
@endsection
