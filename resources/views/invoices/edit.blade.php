@extends('layouts.app')
@section('title', 'ویرایش صورتحساب')
@section('page-title', 'ویرایش صورتحساب')
@section('page-subtitle', $invoice->number)
@section('page-actions')
    <a href="{{ route('invoices.show', $invoice) }}" class="btn-secondary">
        <x-icon name="arrow-left" class="size-4 rotate-180" />
        <span class="hidden sm:inline">بازگشت</span>
    </a>
    @can('delete', $invoice)
        <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" data-confirm="این پیش‌نویس صورتحساب حذف شود؟ این عملیات قابل بازگشت نیست.">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-danger">
                <x-icon name="trash" class="size-4" />
                <span class="hidden sm:inline">حذف صورتحساب</span>
            </button>
        </form>
    @endcan
@endsection
@section('content')
    <form method="POST" action="{{ route('invoices.update', $invoice) }}" class="card p-5 sm:p-7" data-invoice-form>
        @csrf
        @method('PUT')
        <x-invoices.form :invoice="$invoice" :customers="$customers" :goods="$goods" :suggested-number="$suggestedNumber" />
        <div class="mt-7 flex justify-end gap-3 border-t border-slate-100 pt-6">
            <a href="{{ route('invoices.show', $invoice) }}" class="btn-secondary">انصراف</a>
            <button class="btn-primary">ذخیره تغییرات</button>
        </div>
    </form>
@endsection
