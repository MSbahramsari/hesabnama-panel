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
            $table->foreignId('customer_id')->nullable()->change();
            $table->unsignedTinyInteger('electronic_invoice_type')->default(1)->after('invoice_type')->index();
            $table->string('payment_switch_number', 9)->nullable()->after('cash_amount');
            $table->string('payment_acceptor_number', 14)->nullable()->after('payment_switch_number');
            $table->string('payment_terminal_number', 8)->nullable()->after('payment_acceptor_number');
            $table->unsignedTinyInteger('payment_method')->nullable()->after('payment_terminal_number');
            $table->string('payment_tracking_number', 14)->nullable()->after('payment_method');
            $table->string('payer_card_number', 16)->nullable()->after('payment_tracking_number');
            $table->string('payer_identifier', 12)->nullable()->after('payer_card_number');
            $table->timestamp('payment_at')->nullable()->after('payer_identifier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable(false)->change();
            $table->dropColumn([
                'electronic_invoice_type', 'payment_switch_number', 'payment_acceptor_number',
                'payment_terminal_number', 'payment_method', 'payment_tracking_number',
                'payer_card_number', 'payer_identifier', 'payment_at',
            ]);
        });
    }
};
