@extends('layouts.app')

@section('title', 'کالا و خدمات')
@section('page-title', 'کالا و خدمات')
@section('page-subtitle', 'فهرست اقلام قابل استفاده در صورتحساب')

@section('page-actions')
    <a href="{{ route('goods.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /><span class="hidden sm:inline">قلم جدید</span></a>
@endsection

@section('content')
    <div class="card">
        <div class="table-toolbar">
            <form method="GET" class="flex w-full max-w-lg gap-2">
                <div class="relative flex-1">
                    <x-icon name="search" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <input name="q" value="{{ $search }}" class="form-control pr-10" placeholder="عنوان، شناسه عمومی یا اختصاصی">
                </div>
                <button class="btn-secondary">جست‌وجو</button>
                @if($search)<a href="{{ route('goods.index') }}" class="btn-secondary px-3" title="پاک کردن جست‌وجو">×</a>@endif
            </form>
            <div class="table-count"><span class="size-1.5 rounded-full bg-violet-500"></span><strong>{{ number_format($goods->total()) }}</strong> قلم ثبت‌شده</div>
        </div>

        @if($goods->isEmpty())
            <x-empty-state title="کالا یا خدمتی پیدا نشد" description="شناسه کالا را استعلام کنید و اولین قلم را بسازید." :action="route('goods.create')" action-label="افزودن قلم" />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>عنوان قلم</th><th>شناسه کالا/خدمت</th><th>واحد</th><th>قیمت واحد</th><th>نرخ مالیات</th><th>وضعیت</th><th class="table-actions-cell">عملیات</th></tr></thead>
                    <tbody>
                        @foreach($goods as $good)
                            <tr>
                                <td><div class="table-primary">{{ $good->name }}</div><div class="table-meta">قابل استفاده در صورتحساب</div></td>
                                <td dir="ltr" class="text-right"><span class="table-code">{{ $good->commodity_code }}</span></td>
                                <td>{{ $good->unit }}</td>
                                <td class="table-number">{{ number_format($good->unit_price) }}<small>ریال</small></td>
                                <td class="table-number">{{ number_format($good->tax_rate, 0) }}<small>٪</small></td>
                                <td><span @class(['status-badge', 'status-emerald' => $good->is_active, 'status-slate' => ! $good->is_active])>{{ $good->is_active ? 'فعال' : 'غیرفعال' }}</span></td>
                                <td class="table-actions-cell"><a href="{{ route('goods.edit', $good) }}" class="table-action"><x-icon name="edit" />ویرایش</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-5 py-4">{{ $goods->links() }}</div>
        @endif
    </div>
@endsection
