<?php

namespace App\Http\Controllers;

use App\Actions\SaveUserWithTaxpayerProfileAction;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $request->user()->load('taxpayerProfile');

        return view('profile.edit');
    }

    public function update(UpdateProfileRequest $request, SaveUserWithTaxpayerProfileAction $action): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            $data = Arr::except($data, ['password']);
        }

        $action->handle($data, $request->user());

        return back()->with('success', 'پروفایل شما به‌روزرسانی شد.');
    }
}
