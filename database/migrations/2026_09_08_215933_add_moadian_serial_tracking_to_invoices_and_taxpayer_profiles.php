<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('moadian_serial')->nullable()->after('submission_uid');
            $table->unique(['user_id', 'moadian_serial']);
        });

        Schema::table('taxpayer_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('next_invoice_serial')->default(1)->after('fiscal_id');
        });

        DB::table('invoices')
            ->select('user_id', DB::raw('MAX(id) as maximum_invoice_id'))
            ->groupBy('user_id')
            ->orderBy('user_id')
            ->each(function (object $row): void {
                DB::table('taxpayer_profiles')
                    ->where('user_id', $row->user_id)
                    ->update(['next_invoice_serial' => ((int) $row->maximum_invoice_id) + 1]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'moadian_serial']);
            $table->dropColumn('moadian_serial');
        });

        Schema::table('taxpayer_profiles', function (Blueprint $table) {
            $table->dropColumn('next_invoice_serial');
        });
    }
};
