@extends('layouts.app')
@section('title', 'صورتحساب جدید')
@section('page-title', 'صورتحساب جدید')
@section('page-subtitle', 'اطلاعات خریدار و اقلام صورتحساب را تکمیل کنید')
@section('page-actions')
    <a href="{{ route('invoices.index') }}" class="btn-secondary">
        <x-icon name="arrow-left" class="size-4 rotate-180" />
        <span class="hidden sm:inline">بازگشت</span>
    </a>
@endsection
@section('content')
    @if($customers->isEmpty() || $goods->isEmpty())
        <div class="mx-auto max-w-3xl rounded-3xl border border-amber-200 bg-amber-50 p-7 text-center"><div class="mx-auto grid size-14 place-items-center rounded-2xl bg-amber-100 text-amber-700"><x-icon name="warning" class="size-7" /></div><h2 class="mt-4 text-xl font-black text-amber-950">پیش‌نیازهای صورتحساب کامل نیست</h2><p class="mt-2 text-sm leading-7 text-amber-800">حداقل یک مشتری و یک کالا یا خدمت فعال نیاز دارید.</p><div class="mt-5 flex flex-wrap justify-center gap-3">@if($customers->isEmpty())<a href="{{ route('customers.create') }}" class="btn-primary">افزودن مشتری</a>@endif @if($goods->isEmpty())<a href="{{ route('goods.create') }}" class="btn-secondary">افزودن کالا</a>@endif</div></div>
    @else
        <form method="POST" action="{{ route('invoices.store') }}" class="card p-5 sm:p-7" data-invoice-form>@csrf<x-invoices.form :customers="$customers" :goods="$goods" :suggested-number="$suggestedNumber" /><div class="mt-7 flex justify-end gap-3 border-t border-slate-100 pt-6"><a href="{{ route('invoices.index') }}" class="btn-secondary">انصراف</a><button class="btn-primary">ذخیره پیش‌نویس</button></div></form>
    @endif
@endsection
