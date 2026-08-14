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
        Schema::create('taxpayer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('taxpayer_name');
            $table->string('taxpayer_type', 20);
            $table->string('national_id', 14);
            $table->string('economic_code', 14);
            $table->char('fiscal_id', 6)->unique();
            $table->string('branch_code', 10)->nullable();
            $table->text('private_key');
            $table->timestamp('connection_verified_at')->nullable();
            $table->timestamps();

            $table->index(['economic_code', 'taxpayer_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxpayer_profiles');
    }
};
