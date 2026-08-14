<?php

namespace App\Http\Controllers;

use App\Exceptions\MoadianApiException;
use App\Exceptions\MoadianConfigurationException;
use App\Services\Moadian\MoadianClientFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaxpayerConnectionController extends Controller
{
    public function __invoke(Request $request, MoadianClientFactory $clientFactory): RedirectResponse
    {
        $user = $request->user();
        $configuration = $clientFactory->configurationForUser($user);

        if (! $configuration->isReal()) {
            return back()->with('error', 'آزمایش اتصال احراز هویت‌شده فقط در حالت واقعی مودیان انجام می‌شود.');
        }

        try {
            $configuration->assertReadyForAuthenticatedRequests();
            $clientFactory->forUser($user)->token();
            $user->taxpayerProfile->update(['connection_verified_at' => now()]);
        } catch (MoadianConfigurationException|MoadianApiException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'اتصال این پرونده مالیاتی و دریافت توکن مودیان با موفقیت تأیید شد.');
    }
}
