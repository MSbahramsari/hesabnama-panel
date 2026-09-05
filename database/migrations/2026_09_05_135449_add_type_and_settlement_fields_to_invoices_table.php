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
            $table->string('invoice_type', 20)->default('original')->index()->after('description');
            $table->string('settlement_method', 20)->default('cash')->index()->after('invoice_type');
            $table->decimal('cash_amount', 18, 2)->default(0)->after('settlement_method');
            $table->foreignId('reference_invoice_id')->nullable()->after('tax_id')->constrained('invoices')->nullOnDelete();
            $table->index(['user_id', 'invoice_type', 'invoice_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'invoice_type', 'invoice_date']);
            $table->dropConstrainedForeignId('reference_invoice_id');
            $table->dropColumn(['invoice_type', 'settlement_method', 'cash_amount']);
        });
    }
};
