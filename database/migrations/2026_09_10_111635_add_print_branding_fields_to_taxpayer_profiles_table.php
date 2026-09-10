<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('taxpayer_profiles', function (Blueprint $table) {
            $table->string('address', 1000)->nullable()->after('branch_code');
            $table->string('postal_code', 10)->nullable()->after('address');
            $table->string('phone', 20)->nullable()->after('postal_code');
            $table->string('company_logo_path')->nullable()->after('phone');
            $table->string('stamp_signature_path')->nullable()->after('company_logo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('taxpayer_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'postal_code',
                'phone',
                'company_logo_path',
                'stamp_signature_path',
            ]);
        });
    }
};
