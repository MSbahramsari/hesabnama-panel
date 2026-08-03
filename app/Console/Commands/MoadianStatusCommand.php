<?php

namespace App\Console\Commands;

use App\Exceptions\MoadianApiException;
use App\Exceptions\MoadianConfigurationException;
use App\Services\Moadian\MoadianClient;
use App\Services\Moadian\MoadianConfiguration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('moadian:status')]
#[Description('Check local configuration and connectivity to the Iranian taxpayer system')]
class MoadianStatusCommand extends Command
{
    public function handle(MoadianConfiguration $configuration, MoadianClient $client): int
    {
        $this->components->info('بررسی اتصال سامانه مودیان');
        $this->line('حالت درگاه: '.($configuration->isReal() ? 'واقعی' : 'آزمایشی'));

        try {
            $client->encryptionKey();
            $this->components->info('اتصال به سرور رسمی و دریافت کلید عمومی: موفق');
        } catch (MoadianApiException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

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
