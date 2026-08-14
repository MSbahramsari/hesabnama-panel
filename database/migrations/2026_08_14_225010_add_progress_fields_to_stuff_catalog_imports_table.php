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
        Schema::table('stuff_catalog_imports', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('status');
            $table->unsignedBigInteger('file_size')->default(0)->after('progress_percent');
            $table->unsignedBigInteger('processed_bytes')->default(0)->after('file_size');
            $table->unsignedBigInteger('processed_rows')->default(0)->after('processed_bytes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stuff_catalog_imports', function (Blueprint $table) {
            $table->dropColumn([
                'progress_percent',
                'file_size',
                'processed_bytes',
                'processed_rows',
            ]);
        });
    }
};
