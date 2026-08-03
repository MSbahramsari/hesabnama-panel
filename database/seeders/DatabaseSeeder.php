<?php

namespace Database\Seeders;

use App\Enums\Plan;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        User::updateOrCreate(
            ['email' => 'admin@moadian.test'],
            [
                'name' => 'مدیر سامانه',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'plan' => Plan::Enterprise,
                'permissions' => [],
                'license_expires_at' => null,
                'is_active' => true,
            ],
        );

        $demoUser = User::updateOrCreate(
            ['email' => 'demo@moadian.test'],
            [
                'name' => 'شرکت دمو مودیان',
                'password' => Hash::make('password'),
                'role' => UserRole::Member,
                'plan' => Plan::Business,
                'permissions' => ['customers', 'goods', 'invoices'],
                'license_expires_at' => now()->addYear(),
                'is_active' => true,
            ],
        );

        $customer = $demoUser->customers()->updateOrCreate(
            ['economic_code' => '411111111111'],
            [
                'national_id' => '14001234567',
                'name' => 'شرکت راهکار مالی آریا',
                'type' => 'legal',
                'address' => 'تهران، میدان ونک، خیابان ملاصدرا',
                'postal_code' => '1991912345',
                'phone' => '02188776655',
                'is_active' => true,
            ],
        );

        $good = $demoUser->goods()->updateOrCreate(
            ['commodity_code' => '10000001'],
            ['name' => 'خدمات مشاوره مالیاتی', 'unit' => 'ساعت', 'unit_price' => 25000000, 'tax_rate' => 10, 'is_active' => true],
        );

        $demoUser->goods()->updateOrCreate(
            ['commodity_code' => '10000002'],
            ['name' => 'نرم‌افزار حسابداری ابری', 'unit' => 'اشتراک', 'unit_price' => 85000000, 'tax_rate' => 10, 'is_active' => true],
        );

        $invoice = Invoice::updateOrCreate(
            ['user_id' => $demoUser->id, 'number' => 'INV-DEMO-0001'],
            [
                'customer_id' => $customer->id,
                'invoice_date' => today(),
                'description' => 'فاکتور نمونه جهت بررسی جریان کار',
                'status' => 'draft',
                'subtotal' => 50000000,
                'discount_total' => 0,
                'tax_total' => 5000000,
                'total' => 55000000,
            ],
        );

        $invoice->items()->updateOrCreate(
            ['commodity_code' => $good->commodity_code],
            [
                'good_id' => $good->id,
                'description' => $good->name,
                'quantity' => 2,
                'unit_price' => 25000000,
                'tax_rate' => 10,
                'discount' => 0,
                'subtotal' => 50000000,
                'tax_amount' => 5000000,
                'total' => 55000000,
            ],
        );
    }
}
