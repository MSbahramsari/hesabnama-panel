<?php

namespace App\Console\Commands;

use App\Enums\Plan;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('admin:create {email : Administrator email address} {--name= : Administrator display name}')]
#[Description('Create the initial production administrator securely')]
class CreateAdminCommand extends Command
{
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error('نشانی ایمیل معتبر نیست.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->components->error('کاربری با این ایمیل قبلاً ثبت شده است.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: $this->ask('نام مدیر')));

        if ($name === '' || Str::length($name) > 255) {
            $this->components->error('نام مدیر باید بین ۱ تا ۲۵۵ کاراکتر باشد.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('رمز عبور مدیر (حداقل ۱۲ کاراکتر)');
        $confirmation = (string) $this->secret('تکرار رمز عبور مدیر');

        if (Str::length($password) < 12) {
            $this->components->error('رمز عبور باید حداقل ۱۲ کاراکتر باشد.');

            return self::FAILURE;
        }

        if (! hash_equals($password, $confirmation)) {
            $this->components->error('تکرار رمز عبور مطابقت ندارد.');

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
            'plan' => Plan::Enterprise,
            'permissions' => [],
            'license_expires_at' => null,
            'is_active' => true,
        ]);

        $this->components->info('حساب مدیر با موفقیت ایجاد شد.');

        return self::SUCCESS;
    }
}
