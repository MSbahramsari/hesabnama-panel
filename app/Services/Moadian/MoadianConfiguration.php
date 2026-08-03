<?php

namespace App\Services\Moadian;

use App\Exceptions\MoadianConfigurationException;

class MoadianConfiguration
{
    public function isReal(): bool
    {
        return $this->driver() === 'real';
    }

    public function driver(): string
    {
        return (string) config('services.moadian.driver', 'demo');
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('services.moadian.base_url'), '/');
    }

    public function fiscalId(): string
    {
        return mb_strtoupper(trim((string) config('services.moadian.fiscal_id')));
    }

    public function sellerEconomicCode(): string
    {
        return trim((string) config('services.moadian.seller_economic_code'));
    }

    public function sellerBranchCode(): ?string
    {
        $branchCode = trim((string) config('services.moadian.seller_branch_code'));

        return $branchCode !== '' ? $branchCode : null;
    }

    public function defaultMeasurementUnitCode(): ?string
    {
        $unitCode = trim((string) config('services.moadian.default_measurement_unit_code'));

        return $unitCode !== '' ? $unitCode : null;
    }

    public function timeout(): int
    {
        return (int) config('services.moadian.timeout', 20);
    }

    public function connectTimeout(): int
    {
        return (int) config('services.moadian.connect_timeout', 5);
    }

    public function privateKeyPath(): string
    {
        return trim((string) config('services.moadian.private_key_path'));
    }

    public function caBundlePath(): ?string
    {
        $path = trim((string) config('services.moadian.ca_bundle_path'));

        return $path !== '' ? $path : null;
    }

    public function isReady(): bool
    {
        try {
            $this->assertReadyForSubmission();

            return true;
        } catch (MoadianConfigurationException) {
            return false;
        }
    }

    public function assertReadyForAuthenticatedRequests(): void
    {
        if (! preg_match('/^[A-Z0-9]{6}$/', $this->fiscalId())) {
            throw new MoadianConfigurationException('شناسه یکتای حافظه مالیاتی باید دقیقاً ۶ کاراکتر انگلیسی یا عدد باشد.');
        }

        $this->privateKeyPem();
    }

    public function assertReadyForSubmission(): void
    {
        $this->assertReadyForAuthenticatedRequests();

        if (! preg_match('/^\d{10,14}$/', $this->sellerEconomicCode())) {
            throw new MoadianConfigurationException('شماره اقتصادی فروشنده در تنظیمات اتصال وارد نشده یا معتبر نیست.');
        }
    }

    public function privateKeyPem(): string
    {
        $path = $this->privateKeyPath();

        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new MoadianConfigurationException('فایل کلید خصوصی سامانه مودیان در مسیر تنظیم‌شده قابل خواندن نیست.');
        }

        $privateKey = file_get_contents($path);

        if ($privateKey === false || openssl_pkey_get_private($privateKey) === false) {
            throw new MoadianConfigurationException('فایل کلید خصوصی سامانه مودیان معتبر نیست.');
        }

        return $privateKey;
    }
}
