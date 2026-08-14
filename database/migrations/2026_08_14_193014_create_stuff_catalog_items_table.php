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
        Schema::create('stuff_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_id', 20);
            $table->text('description');
            $table->string('type', 80)->nullable()->index();
            $table->decimal('vat', 5, 2)->default(0)->index();
            $table->string('source_created_date', 40)->nullable();
            $table->string('effective_date', 40)->nullable();
            $table->string('expiration_date', 40)->nullable();
            $table->string('source_updated_date', 40)->nullable();
            $table->char('source_hash', 64)->unique();
            $table->timestamps();

            $table->index(['item_id', 'effective_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stuff_catalog_items');
    }
};
