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
            $table->string('moadian_status')->nullable()->index()->after('status');
            $table->text('moadian_tax_result')->nullable()->after('moadian_status');
            $table->string('moadian_confirmation_reference_id', 128)->nullable()->after('reference_number');
            $table->string('moadian_packet_type', 80)->nullable()->after('moadian_confirmation_reference_id');
            $table->string('buyer_status_source', 30)->nullable()->after('buyer_status');
            $table->timestamp('buyer_status_updated_at')->nullable()->after('buyer_status_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['moadian_status']);
            $table->dropColumn([
                'moadian_status',
                'moadian_tax_result',
                'moadian_confirmation_reference_id',
                'moadian_packet_type',
                'buyer_status_source',
                'buyer_status_updated_at',
            ]);
        });
    }
};
