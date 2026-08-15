@extends('layouts.app')
@section('title', 'ویرایش مشتری')
@section('page-title', 'ویرایش مشتری')
@section('page-subtitle', $customer->name)
@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        <div class="page-content-actions">
            <a href="{{ route('customers.index') }}" class="btn-secondary"><x-icon name="arrow-left" class="size-4 rotate-180" />بازگشت</a>
            @can('delete', $customer)
                <form method="POST" action="{{ route('customers.destroy', $customer) }}" data-confirm="این مشتری حذف شود؟ این عملیات قابل بازگشت نیست.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger"><x-icon name="trash" class="size-4" />حذف مشتری</button>
                </form>
            @endcan
        </div>
        <form method="POST" action="{{ route('customers.update', $customer) }}" class="card p-5 sm:p-7">
            @csrf
            @method('PUT')
            <x-customers.form :customer="$customer" />
            <div class="mt-7 flex justify-end gap-3">
                <a href="{{ route('customers.index') }}" class="btn-secondary">انصراف</a>
                <button class="btn-primary">ذخیره تغییرات</button>
            </div>
        </form>
    </div>
@endsection
