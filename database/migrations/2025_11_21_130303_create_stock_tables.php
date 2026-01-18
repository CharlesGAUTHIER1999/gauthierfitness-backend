<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // stock lots
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('lot_number');
            $table->date('expiration_date')->nullable();

            $table->integer('initial_quantity')->nullable();
            $table->integer('quantity')->default(0);

            $table->timestamps();

            $table->index(['product_id', 'expiration_date']);
        });

        // stock movements
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lot_id')
                ->nullable()
                ->constrained('stock_lots')
                ->nullOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->enum('type', ['in', 'out', 'correction']);
            $table->integer('quantity');
            $table->string('reason')->nullable();

            $table->timestamps();
        });

        // stock reservations (cart / checkout)
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('lot_id')
                ->nullable()
                ->constrained('stock_lots')
                ->nullOnDelete();

            $table
                ->unsignedBigInteger('order_id')
                ->nullable();

            $table->integer('quantity');
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_lots');
    }
};
