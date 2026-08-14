@extends('layouts.app')
@section('title', 'ویرایش کاربر')
@section('page-title', 'ویرایش کاربر')
@section('page-subtitle', $user->name)
@section('content')<form method="POST" action="{{ route('admin.users.update', $user) }}" enctype="multipart/form-data" class="card mx-auto max-w-5xl p-5 sm:p-7">@csrf @method('PUT')<x-admin.user-form :user="$user" :roles="$roles" :plans="$plans" :permissions="$permissions" /><div class="mt-7 flex justify-end gap-3"><a href="{{ route('admin.users.index') }}" class="btn-secondary">انصراف</a><button class="btn-primary">ذخیره تغییرات</button></div></form>@endsection
