<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member')->index();
            $table->string('plan')->default('starter');
            $table->json('permissions')->nullable();
            $table->timestamp('license_expires_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'plan', 'permissions', 'license_expires_at', 'is_active']);
        });
    }
};
