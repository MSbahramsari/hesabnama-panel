<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidPrivateKey implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('فایل کلید خصوصی قابل خواندن نیست.');

            return;
        }

        $privateKey = file_get_contents($value->getRealPath());
        $key = is_string($privateKey) ? openssl_pkey_get_private($privateKey) : false;

        if ($key === false) {
            $fail('فایل انتخاب‌شده یک کلید خصوصی PEM معتبر و بدون رمز نیست.');

            return;
        }

        $details = openssl_pkey_get_details($key);

        if (! is_array($details) || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            $fail('کلید خصوصی سامانه مودیان باید از نوع RSA باشد.');
        }
    }
}
