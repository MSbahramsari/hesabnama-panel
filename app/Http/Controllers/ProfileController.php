<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit');
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            $data = Arr::except($data, ['password']);
        }

        $request->user()->update($data);

        return back()->with('success', 'پروفایل شما به‌روزرسانی شد.');
    }
}
