# استقرار نسخه Production

## پیش‌نیازهای سرور

- PHP 8.4 به‌همراه افزونه‌های `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`
- Composer 2
- MySQL 8 یا MariaDB 10.6 به بالا
- HTTPS معتبر
- تنظیم Document Root دامنه روی پوشه `public`

## نصب اولیه

```bash
cp .env.production.example .env
composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate --force
php artisan migrate --force
php artisan admin:create admin@example.com --name="System Administrator"
php artisan optimize
```

پوشه‌های زیر باید برای کاربر وب‌سرور قابل نوشتن باشند:

```text
storage
bootstrap/cache
```

فایل کلید خصوصی را بیرون از `public` قرار دهید، مالک آن را کاربر سرویس PHP قرار دهید و دسترسی آن را روی `600` تنظیم کنید.

## تنظیم اتصال سامانه مودیان

مقادیر زیر باید در `.env` واقعی سرور تکمیل شوند:

```dotenv
MOADIAN_DRIVER=real
MOADIAN_FISCAL_ID=ABC123
MOADIAN_SELLER_ECONOMIC_CODE=12345678901
MOADIAN_PRIVATE_KEY_PATH=/absolute/path/to/private.pem
```

سپس اتصال را بررسی و کش production را بازسازی کنید:

```bash
php artisan optimize:clear
php artisan moadian:status
php artisan optimize
```

دستور `moadian:status` باید دریافت کلید عمومی و توکن را موفق اعلام کند. تا قبل از تکمیل شناسه حافظه مالیاتی و شماره اقتصادی، ارسال واقعی عمداً غیرفعال می‌ماند.

## تنظیم Nginx

```nginx
root /var/www/moadian/current/public;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.4-fpm.sock;
}

location ~ /\.(?!well-known).* {
    deny all;
}
```

در Apache باید Document Root روی `public` باشد و `mod_rewrite` فعال شود؛ فایل `public/.htaccess` داخل بسته قرار دارد.

## انتشار نسخه‌های بعدی

```bash
php artisan down
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize
php artisan up
```

اگر تنظیمات `.env` تغییر کرد، پیش از `optimize` دستور `php artisan optimize:clear` اجرا شود.

## کنترل سلامت

پس از استقرار این موارد بررسی شوند:

```text
GET /up
ورود مدیر
ثبت مشتری و کالا
ساخت صورتحساب
php artisan moadian:status
```

برای نخستین ارسال واقعی، از یک صورتحساب معتبر و کم‌ریسک استفاده کنید و پس از دریافت `referenceNumber` وضعیت آن را از همان صفحه استعلام بگیرید.
