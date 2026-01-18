<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['size', 'format', 'capacity']);
            $table->string('code', 80);
            $table->decimal('price_ht', 10, 2)->nullable();
            $table->decimal('price_ttc', 10, 2)->nullable();
            $table->decimal('vat', 5, 2)->default(20.00);
            $table->string('label', 120)->nullable();
            $table->integer('position')->default(0);
            $table->json('meta')->nullable();
            $table->string('sku', 80)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['product_id', 'type', 'code']);
            $table->index(['product_id', 'type', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_options');
    }
};
