@extends('layouts.app')
@section('title', 'ویرایش قلم')
@section('page-title', 'ویرایش کالا یا خدمت')
@section('page-subtitle', $good->name)
@section('content')<form method="POST" action="{{ route('goods.update', $good) }}" class="card mx-auto max-w-4xl p-5 sm:p-7">@csrf @method('PUT')<x-goods.form :good="$good" /><div class="mt-7 flex justify-end gap-3"><a href="{{ route('goods.index') }}" class="btn-secondary">انصراف</a><button class="btn-primary">ذخیره تغییرات</button></div></form>@endsection
