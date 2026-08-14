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

## تنظیم اتصال سامانه مودیان

فقط تنظیمات عمومی وب‌سرویس در `.env` سرور قرار می‌گیرند:

```dotenv
MOADIAN_DRIVER=real
MOADIAN_BASE_URL=https://tp.tax.gov.ir/req/api/self-tsp
```

شناسه حافظه مالیاتی، کد اقتصادی، کد شعبه و کلید خصوصی برای هر حساب از فرم ایجاد کاربر یا پروفایل همان کاربر ثبت می‌شوند. کلید خصوصی به‌صورت رمزنگاری‌شده در دیتابیس نگهداری می‌شود؛ بنابراین `APP_KEY` تولیدشده را پس از ثبت کلیدها تغییر ندهید.

سپس کش production را بازسازی و اتصال حساب موردنظر را با ایمیل آن بررسی کنید:

```bash
php artisan optimize:clear
php artisan moadian:status user@example.com
php artisan optimize
```

دستور `moadian:status` باید دریافت کلید عمومی و توکن همان پرونده مالیاتی را موفق اعلام کند. کاربران همچنین می‌توانند از صفحه پروفایل خود دکمه «آزمایش اتصال و توکن» را اجرا کنند.

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
php artisan moadian:status user@example.com
```

برای نخستین ارسال واقعی، از یک صورتحساب معتبر و کم‌ریسک استفاده کنید و پس از دریافت `referenceNumber` وضعیت آن را از همان صفحه استعلام بگیرید.
