@extends('layouts.app')
@section('title', 'ویرایش مشتری')
@section('page-title', 'ویرایش مشتری')
@section('page-subtitle', $customer->name)
@section('page-actions')
    <a href="{{ route('customers.index') }}" class="btn-secondary">
        <x-icon name="arrow-left" class="size-4 rotate-180" />
        <span class="hidden sm:inline">بازگشت</span>
    </a>
    @can('delete', $customer)
        <form method="POST" action="{{ route('customers.destroy', $customer) }}" data-confirm="این مشتری حذف شود؟ این عملیات قابل بازگشت نیست.">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-danger">
                <x-icon name="trash" class="size-4" />
                <span class="hidden sm:inline">حذف مشتری</span>
            </button>
        </form>
    @endcan
@endsection
@section('content')
    <form method="POST" action="{{ route('customers.update', $customer) }}" class="card mx-auto max-w-4xl p-5 sm:p-7">
        @csrf
        @method('PUT')
        <x-customers.form :customer="$customer" />
        <div class="mt-7 flex justify-end gap-3">
            <a href="{{ route('customers.index') }}" class="btn-secondary">انصراف</a>
            <button class="btn-primary">ذخیره تغییرات</button>
        </div>
    </form>
@endsection
