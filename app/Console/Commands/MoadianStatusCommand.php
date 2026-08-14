<?php

namespace App\Console\Commands;

use App\Exceptions\MoadianApiException;
use App\Exceptions\MoadianConfigurationException;
use App\Models\User;
use App\Services\Moadian\MoadianClientFactory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('moadian:status {email? : ایمیل حسابی که اتصال پرونده مالیاتی آن بررسی می‌شود}')]
#[Description('Check local configuration and connectivity to the Iranian taxpayer system')]
class MoadianStatusCommand extends Command
{
    public function handle(MoadianClientFactory $clientFactory): int
    {
        $publicConfiguration = $clientFactory->configuration(null);
        $this->components->info('بررسی اتصال سامانه مودیان');
        $this->line('حالت درگاه: '.($publicConfiguration->isReal() ? 'واقعی' : 'آزمایشی'));

        try {
            $clientFactory->publicClient()->encryptionKey();
            $this->components->info('اتصال به سرور رسمی و دریافت کلید عمومی: موفق');
        } catch (MoadianApiException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $email = trim((string) $this->argument('email'));

        if ($email === '') {
            $this->components->warn('برای بررسی احراز هویت یک مودی، ایمیل همان حساب را به دستور اضافه کنید.');
            $this->line('نمونه: php artisan moadian:status user@example.com');

            return self::SUCCESS;
        }

        $user = User::query()->with('taxpayerProfile')->where('email', $email)->first();

        if ($user === null) {
            $this->components->error('کاربری با این ایمیل یافت نشد.');

            return self::FAILURE;
        }

        $configuration = $clientFactory->configurationForUser($user);
        $client = $clientFactory->forUser($user);

        try {
            $configuration->assertReadyForAuthenticatedRequests();
            $this->components->info('کلید خصوصی و شناسه حافظه مالیاتی: معتبر');
        } catch (MoadianConfigurationException $exception) {
            $this->components->warn($exception->getMessage());
            $this->components->warn('ارسال واقعی تا تکمیل تنظیمات غیرفعال باقی می‌ماند.');

            return self::SUCCESS;
        }

        try {
            $client->token();
            $this->components->info('احراز هویت و دریافت توکن: موفق');
            $user->taxpayerProfile?->update(['connection_verified_at' => now()]);
        } catch (MoadianApiException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $configuration->isReady()) {
            $this->components->warn('شماره اقتصادی فروشنده هنوز تکمیل نشده و ارسال واقعی غیرفعال است.');

            return self::SUCCESS;
        }

        $this->components->info('تنظیمات ارسال واقعی کامل است.');

        return self::SUCCESS;
    }
}
