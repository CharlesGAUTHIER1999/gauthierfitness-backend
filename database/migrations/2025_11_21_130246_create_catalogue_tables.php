<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // suppliers
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->timestamps();
        });

        // categories (hierarchical)
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type'); // clothing | nutrition | equipment
            $table->integer('position')->default(0);

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->timestamps();
        });

        // products
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('supplier_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('brand')->nullable();
            $table->string('origin')->nullable();

            $table->text('description')->nullable();

            $table->decimal('price_ht', 10, 2);
            $table->decimal('price_ttc', 10, 2);
            $table->decimal('vat', 5, 2)->default(20.00);

            $table->string('sku', 50)->unique();
            $table->string('barcode', 50)->nullable();

            $table->decimal('weight', 10, 3)->nullable();

            // tailles, goûts, couleurs, etc.
            $table->json('attributes')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        // pivot product_category
        Schema::create('product_category', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['product_id', 'category_id']);
        });

        // product images
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('url');
            $table->boolean('is_main')->default(false);
            $table->integer('position')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_category');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('suppliers');
    }
};
