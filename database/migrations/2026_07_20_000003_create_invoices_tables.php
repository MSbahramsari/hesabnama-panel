<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('number', 50);
            $table->date('invoice_date')->index();
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('buyer_status')->nullable()->index();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->uuid('submission_uid')->nullable()->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'number']);
            $table->index(['user_id', 'status', 'created_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('good_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('commodity_code', 30);
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2);
            $table->decimal('tax_amount', 18, 2);
            $table->decimal('total', 18, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
