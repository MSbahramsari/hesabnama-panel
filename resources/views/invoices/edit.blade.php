@extends('layouts.app')
@section('title', 'ویرایش صورتحساب')
@section('page-title', 'ویرایش صورتحساب')
@section('page-subtitle', $invoice->number)
@section('content')<form method="POST" action="{{ route('invoices.update', $invoice) }}" class="card p-5 sm:p-7" data-invoice-form>@csrf @method('PUT')<x-invoices.form :invoice="$invoice" :customers="$customers" :goods="$goods" :suggested-number="$suggestedNumber" /><div class="mt-7 flex justify-end gap-3 border-t border-slate-100 pt-6"><a href="{{ route('invoices.show', $invoice) }}" class="btn-secondary">انصراف</a><button class="btn-primary">ذخیره تغییرات</button></div></form>@endsection
