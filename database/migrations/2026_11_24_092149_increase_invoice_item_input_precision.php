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
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('quantity', 26, 8)->change();
            $table->decimal('unit_price', 26, 8)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('invoice_items')
            ->whereRaw('quantity <> ROUND(quantity, 3) OR unit_price <> ROUND(unit_price, 2) OR quantity > 99999999999.999 OR unit_price > 9999999999999999.99')
            ->exists()) {
            throw new RuntimeException('Cannot reduce invoice precision without losing existing values.');
        }
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('quantity', 14, 3)->change();
            $table->decimal('unit_price', 18, 2)->change();
        });
    }
};
