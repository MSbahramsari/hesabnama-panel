@extends('layouts.app')

@section('title', 'کاربران و مجوزها')
@section('page-title', 'کاربران و مجوزها')
@section('page-subtitle', 'ساخت حساب، تعیین پلن، دسترسی و تاریخ انقضا')

@section('page-actions')
    <a href="{{ route('admin.users.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /><span class="hidden sm:inline">کاربر جدید</span></a>
@endsection

@section('content')
    <div class="card">
        <div class="table-toolbar">
            <form method="GET" class="flex w-full max-w-lg gap-2">
                <div class="relative flex-1">
                    <x-icon name="search" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                    <input name="q" value="{{ $search }}" class="form-control pr-10" placeholder="نام یا نشانی ایمیل">
                </div>
                <button class="btn-secondary">جست‌وجو</button>
                @if($search)<a href="{{ route('admin.users.index') }}" class="btn-secondary px-3" title="پاک کردن جست‌وجو">×</a>@endif
            </form>
            <div class="table-count"><span class="size-1.5 rounded-full bg-blue-500"></span><strong>{{ number_format($users->total()) }}</strong> حساب کاربری</div>
        </div>

        <div class="table-wrap">
            <table class="data-table min-w-[980px]">
                <thead><tr><th>کاربر</th><th>نقش سازمانی</th><th>پلن</th><th>پایان اعتبار</th><th>دسترسی‌ها</th><th>وضعیت</th><th class="table-actions-cell">عملیات</th></tr></thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td><div class="table-primary">{{ $user->name }}</div><div dir="ltr" class="table-meta justify-end">{{ $user->email }}</div></td>
                            <td><span class="rounded-lg bg-blue-50 px-2.5 py-1.5 text-[10px] font-bold text-blue-700">{{ $user->role->label() }}</span></td>
                            <td class="font-bold text-slate-700">{{ $user->plan->label() }}</td>
                            <td dir="ltr" class="table-number text-right">{{ $user->license_expires_at?->format('Y/m/d') ?? 'بدون انقضا' }}</td>
                            <td>
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse($user->permissions ?? [] as $permission)
                                        <span class="rounded-md bg-slate-100 px-2 py-1 text-[9px] font-bold text-slate-600 ring-1 ring-inset ring-slate-200/60">{{ ['customers' => 'مشتری', 'goods' => 'کالا', 'invoices' => 'فاکتور'][$permission] ?? $permission }}</span>
                                    @empty
                                        <span class="text-xs text-slate-400">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td><span @class(['status-badge', 'status-emerald' => $user->is_active && $user->hasActiveLicense(), 'status-rose' => ! $user->is_active || ! $user->hasActiveLicense()])>{{ $user->is_active && $user->hasActiveLicense() ? 'فعال' : 'غیرفعال' }}</span></td>
                            <td class="table-actions-cell"><a href="{{ route('admin.users.edit', $user) }}" class="table-action"><x-icon name="edit" />ویرایش</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $users->onEachSide(1)->links('components.pagination') }}
    </div>
@endsection
