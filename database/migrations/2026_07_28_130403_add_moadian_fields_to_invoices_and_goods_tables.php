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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('tax_id', 22)->nullable()->unique()->after('submission_uid');
            $table->uuid('reference_number')->nullable()->unique()->after('tax_id');
            $table->timestamp('last_inquired_at')->nullable()->after('sent_at');
        });

        Schema::table('goods', function (Blueprint $table) {
            $table->string('measurement_unit_code', 10)->nullable()->after('unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods', function (Blueprint $table) {
            $table->dropColumn('measurement_unit_code');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['tax_id']);
            $table->dropUnique(['reference_number']);
            $table->dropColumn(['tax_id', 'reference_number', 'last_inquired_at']);
        });
    }
};
