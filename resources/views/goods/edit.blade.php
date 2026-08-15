@extends('layouts.app')
@section('title', 'ویرایش قلم')
@section('page-title', 'ویرایش کالا یا خدمت')
@section('page-subtitle', $good->name)
@section('content')
    <div class="mx-auto max-w-4xl space-y-4">
        <div class="page-content-actions">
            <a href="{{ route('goods.index') }}" class="btn-secondary"><x-icon name="arrow-left" class="size-4 rotate-180" />بازگشت</a>
            @can('delete', $good)
                <form method="POST" action="{{ route('goods.destroy', $good) }}" data-confirm="این کالا یا خدمت حذف شود؟ این عملیات قابل بازگشت نیست.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger"><x-icon name="trash" class="size-4" />حذف قلم</button>
                </form>
            @endcan
        </div>
        <form method="POST" action="{{ route('goods.update', $good) }}" class="card p-5 sm:p-7">
            @csrf
            @method('PUT')
            <x-goods.form :good="$good" />
            <div class="mt-7 flex justify-end gap-3">
                <a href="{{ route('goods.index') }}" class="btn-secondary">انصراف</a>
                <button class="btn-primary">ذخیره تغییرات</button>
            </div>
        </form>
    </div>
@endsection
