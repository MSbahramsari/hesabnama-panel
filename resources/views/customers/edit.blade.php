@extends('layouts.app')
@section('title', 'ویرایش مشتری')
@section('page-title', 'ویرایش مشتری')
@section('page-subtitle', $customer->name)
@section('content')<form method="POST" action="{{ route('customers.update', $customer) }}" class="card mx-auto max-w-4xl p-5 sm:p-7">@csrf @method('PUT')<x-customers.form :customer="$customer" /><div class="mt-7 flex justify-end gap-3"><a href="{{ route('customers.index') }}" class="btn-secondary">انصراف</a><button class="btn-primary">ذخیره تغییرات</button></div></form>@endsection
