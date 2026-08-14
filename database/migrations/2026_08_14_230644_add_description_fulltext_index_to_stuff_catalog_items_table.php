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
        if (! in_array(DB::getDriverName(), ['mariadb', 'mysql'], true)) {
            return;
        }

        Schema::table('stuff_catalog_items', function (Blueprint $table) {
            $table->fullText('description', 'stuff_catalog_description_fulltext');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mariadb', 'mysql'], true)) {
            return;
        }

        Schema::table('stuff_catalog_items', function (Blueprint $table) {
            $table->dropFullText('stuff_catalog_description_fulltext');
        });
    }
};
