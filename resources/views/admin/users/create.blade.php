@extends('layouts.app')
@section('title', 'کاربر جدید')
@section('page-title', 'ساخت کاربر جدید')
@section('page-subtitle', 'حساب، پلن و سطح دسترسی را تعیین کنید')
@section('content')<form method="POST" action="{{ route('admin.users.store') }}" enctype="multipart/form-data" class="card mx-auto max-w-5xl p-5 sm:p-7">@csrf<x-admin.user-form :roles="$roles" :plans="$plans" :permissions="$permissions" /><div class="mt-7 flex justify-end gap-3"><a href="{{ route('admin.users.index') }}" class="btn-secondary">انصراف</a><button class="btn-primary">ساخت حساب</button></div></form>@endsection
