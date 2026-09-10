<?php

namespace App\Http\Controllers;

use App\Actions\SaveUserWithTaxpayerProfileAction;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\TaxpayerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $branding = Arr::only($data, ['company_logo', 'stamp_signature', 'remove_company_logo', 'remove_stamp_signature']);
        $user = $action->handle(Arr::except($data, array_keys($branding)), $request->user());
        $profile = $user->taxpayerProfile;

        if ($profile !== null) {
            $this->updateBrandingAsset($profile, $branding, 'company_logo', 'company_logo_path');
            $this->updateBrandingAsset($profile, $branding, 'stamp_signature', 'stamp_signature_path');
        }

        return back()->with('success', 'پروفایل شما به‌روزرسانی شد.');
    }

    public function branding(Request $request, string $asset): StreamedResponse
    {
        $profile = $request->user()->taxpayerProfile;
        $path = match ($asset) {
            'logo' => $profile?->company_logo_path,
            'stamp-signature' => $profile?->stamp_signature_path,
            default => null,
        };

        abort_if(blank($path) || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Content-Disposition' => 'inline']);
    }

    /** @param array<string, mixed> $branding */
    private function updateBrandingAsset(TaxpayerProfile $profile, array $branding, string $input, string $column): void
    {
        $oldPath = $profile->{$column};

        if (($branding['remove_'.$input] ?? false) && filled($oldPath)) {
            Storage::disk('local')->delete($oldPath);
            $profile->forceFill([$column => null])->save();
            $oldPath = null;
        }

        $file = $branding[$input] ?? null;

        if (! $file instanceof UploadedFile) {
            return;
        }

        $path = $file->store('branding/'.$profile->user_id, 'local');

        if ($path === false) {
            abort(500, 'ذخیره تصویر هویت چاپی انجام نشد.');
        }

        $profile->forceFill([$column => $path])->save();

        if (filled($oldPath)) {
            Storage::disk('local')->delete($oldPath);
        }
    }
}
