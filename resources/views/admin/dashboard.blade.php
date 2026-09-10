@extends('layouts.app')

@section('title', 'مدیریت سامانه')
@section('page-title', 'داشبورد مدیریت')
@section('page-subtitle', 'مدیریت کاربران، دسترسی‌ها و داده‌های مرجع سامانه')

@section('content')
    <section class="dashboard-hero mb-6">
        <div class="absolute -left-20 -top-24 size-72 rounded-full bg-blue-400/15 blur-3xl"></div>
        <div class="relative p-6 sm:p-8 lg:p-9">
            <div class="eyebrow"><span class="size-1.5 rounded-full bg-blue-300"></span>پنل سوپرادمین</div>
            <h2 class="mt-5 text-2xl font-black leading-tight sm:text-[32px]">مدیریت واسط‌نما</h2>
            <p class="mt-3 max-w-2xl text-sm leading-7 text-slate-400">این فضا فقط برای مدیریت کاربران و زیرساخت سامانه است. عملیات مالیاتی هر مودی در حساب خودش انجام می‌شود.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('admin.users.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-xs font-extrabold text-slate-900"><x-icon name="plus" class="size-4" />ساخت حساب کاربری</a>
                <a href="{{ route('admin.stuff-catalog.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-xs font-bold text-slate-200"><x-icon name="box" class="size-4" />مدیریت کاتالوگ رسمی</a>
            </div>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['label' => 'کاربران مودی', 'value' => $metrics['members'], 'caption' => 'کل حساب‌های غیرمدیر', 'color' => 'text-blue-600', 'bg' => 'bg-blue-50'],
            ['label' => 'حساب‌های فعال', 'value' => $metrics['active'], 'caption' => 'دارای مجوز معتبر', 'color' => 'text-emerald-600', 'bg' => 'bg-emerald-50'],
            ['label' => 'رو به انقضا', 'value' => $metrics['expiring'], 'caption' => 'تا ۳۰ روز آینده', 'color' => 'text-amber-600', 'bg' => 'bg-amber-50'],
            ['label' => 'غیرفعال یا منقضی', 'value' => $metrics['inactive'], 'caption' => 'نیازمند بررسی مدیریت', 'color' => 'text-rose-600', 'bg' => 'bg-rose-50'],
        ] as $metric)
            <div class="metric-card {{ $metric['color'] }}">
                <div><div class="metric-label">{{ $metric['label'] }}</div><div class="metric-value">{{ number_format($metric['value']) }}</div><div class="metric-caption">{{ $metric['caption'] }}</div></div>
                <div class="metric-icon {{ $metric['bg'] }}"><x-icon name="users" class="size-5" /></div>
            </div>
        @endforeach
    </section>

    <section class="card mt-6">
        <div class="card-header">
            <div><h3 class="card-title">کاربران تازه ثبت‌شده</h3><p class="card-subtitle">آخرین حساب‌های مودی ایجادشده در سامانه</p></div>
            <a href="{{ route('admin.users.index') }}" class="btn-secondary">مدیریت همه کاربران</a>
        </div>
        @if($recentUsers->isEmpty())
            <x-empty-state title="هنوز کاربری ثبت نشده" description="اولین حساب مودی را ایجاد کنید." :action="route('admin.users.create')" action-label="ساخت حساب" />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>کاربر</th><th>پلن</th><th>اعتبار</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                    <tbody>
                    @foreach($recentUsers as $member)
                        <tr>
                            <td><div class="table-primary">{{ $member->name }}</div><div dir="ltr" class="table-meta justify-end">{{ $member->email }}</div></td>
                            <td>{{ $member->plan->label() }}</td>
                            <td>{{ \App\Support\JalaliDate::format($member->license_expires_at) ?? 'بدون انقضا' }}</td>
                            <td><span class="status-badge {{ $member->hasActiveLicense() ? 'status-emerald' : 'status-rose' }}"><span class="size-1.5 rounded-full bg-current"></span>{{ $member->hasActiveLicense() ? 'فعال' : 'غیرفعال' }}</span></td>
                            <td><a href="{{ route('admin.users.edit', $member) }}" class="table-action"><x-icon name="edit" />مدیریت</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
