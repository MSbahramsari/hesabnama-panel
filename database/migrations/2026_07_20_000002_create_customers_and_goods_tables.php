<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('economic_code', 20);
            $table->string('national_id', 20)->nullable();
            $table->string('name');
            $table->string('type')->default('legal');
            $table->string('address')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['user_id', 'economic_code']);
            $table->index(['user_id', 'name']);
        });

        Schema::create('goods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('commodity_code', 30);
            $table->string('name');
            $table->string('unit', 40)->default('عدد');
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(10);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['user_id', 'commodity_code']);
            $table->index(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods');
        Schema::dropIfExists('customers');
    }
};
