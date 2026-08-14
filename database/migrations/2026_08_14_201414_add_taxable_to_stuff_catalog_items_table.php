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
        if (! Schema::hasColumn('stuff_catalog_items', 'taxable')) {
            Schema::table('stuff_catalog_items', function (Blueprint $table) {
                $table->string('taxable', 40)->nullable()->index()->after('vat');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('stuff_catalog_items', 'taxable')) {
            Schema::table('stuff_catalog_items', function (Blueprint $table) {
                $table->dropColumn('taxable');
            });
        }
    }
};
