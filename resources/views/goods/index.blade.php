@extends('layouts.app')

@section('title', 'کالا و خدمات')
@section('page-title', 'کالا و خدمات')
@section('page-subtitle', 'فهرست اقلام قابل استفاده در صورتحساب')

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
            <div class="flex items-center gap-2">
                <div class="table-count"><span class="size-1.5 rounded-full bg-violet-500"></span><strong>{{ number_format($goods->total()) }}</strong> قلم ثبت‌شده</div>
                <a href="{{ route('goods.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /><span>قلم جدید</span></a>
            </div>
        </div>

        @if($goods->isEmpty())
            <x-empty-state title="کالا یا خدمتی پیدا نشد" description="شناسه کالا را استعلام کنید و اولین قلم را بسازید." :action="route('goods.create')" action-label="افزودن قلم" />
        @else
            <div class="table-wrap goods-table-wrap">
                <table class="data-table goods-table">
                    <colgroup>
                        <col class="w-[27%]">
                        <col class="w-[17%]">
                        <col class="w-[10%]">
                        <col class="w-[14%]">
                        <col class="w-[10%]">
                        <col class="w-[9%]">
                        <col class="w-[13%]">
                    </colgroup>
                    <thead><tr><th>عنوان قلم</th><th>شناسه کالا/خدمت</th><th>واحد</th><th>قیمت واحد</th><th>نرخ مالیات</th><th>وضعیت</th><th class="table-actions-cell">عملیات</th></tr></thead>
                    <tbody>
                        @foreach($goods as $good)
                            <tr>
                                <td data-label="عنوان قلم">
                                    <div class="goods-name-cell">
                                        <span class="goods-name-icon"><x-icon name="box" class="size-4" /></span>
                                        <div class="min-w-0">
                                            <div class="goods-name">{{ $good->name }}</div>
                                            <div class="goods-caption">آماده استفاده در صورتحساب</div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="شناسه کالا/خدمت" dir="ltr" class="text-right"><span class="goods-code">{{ $good->commodity_code }}</span></td>
                                <td data-label="واحد"><span class="goods-unit">{{ $good->unit }}</span></td>
                                <td data-label="قیمت واحد"><span class="goods-price">{{ number_format($good->unit_price) }}</span><small class="goods-price-unit">ریال</small></td>
                                <td data-label="نرخ مالیات"><span @class(['goods-tax', 'goods-tax-exempt' => (float) $good->tax_rate === 0.0, 'goods-taxable' => (float) $good->tax_rate > 0])>{{ number_format($good->tax_rate, 0) }}٪</span></td>
                                <td data-label="وضعیت"><span @class(['status-badge', 'status-emerald' => $good->is_active, 'status-slate' => ! $good->is_active])>{{ $good->is_active ? 'فعال' : 'غیرفعال' }}</span></td>
                                <td data-label="عملیات" class="table-actions-cell">
                                    <div class="table-row-actions">
                                        <a href="{{ route('goods.edit', $good) }}" class="table-action goods-edit-action"><x-icon name="edit" />ویرایش</a>
                                        @can('delete', $good)
                                            <form method="POST" action="{{ route('goods.destroy', $good) }}" data-confirm="این کالا یا خدمت حذف شود؟ این عملیات قابل بازگشت نیست.">
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
            {{ $goods->onEachSide(1)->links('components.pagination') }}
        @endif
    </div>
@endsection
