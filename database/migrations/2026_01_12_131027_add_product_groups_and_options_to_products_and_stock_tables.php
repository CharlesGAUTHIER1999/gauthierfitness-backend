<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) products (group and colors)
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('group_id')
                ->nullable()
                ->constrained('product_groups')
                ->nullOnDelete()
                ->after('supplier_id');
            $table->string('color_code', 40)->nullable()->after('group_id');
            $table->string('color_label', 80)->nullable()->after('color_code');
            $table->index(['group_id', 'color_code'], 'products_group_color_idx');
        });

        // 2) stock_lots: option
        Schema::table('stock_lots', function (Blueprint $table) {
            $table->foreignId('product_option_id')
                ->nullable()
                ->constrained('product_options')
                ->nullOnDelete()
                ->after('product_id');
            $table->index(['product_id', 'product_option_id', 'expiration_date'], 'stock_lots_product_option_exp_idx');
        });

        // 3) stock_movements: option
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('product_option_id')
                ->nullable()
                ->constrained('product_options')
                ->nullOnDelete()
                ->after('product_id');
            $table->index(['product_id', 'product_option_id', 'type'], 'stock_movements_product_option_type_idx');
        });

        // 4) reservations: option
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('product_option_id')
                ->nullable()
                ->constrained('product_options')
                ->nullOnDelete()
                ->after('product_id');
            $table->index(['user_id', 'product_id', 'product_option_id', 'expires_at'], 'reservations_product_option_exp_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_option_id');
            $table->dropIndex('reservations_product_option_exp_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_option_id');
            $table->dropIndex('stock_movements_product_option_type_idx');
        });

        Schema::table('stock_lots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_option_id');
            $table->dropIndex('stock_lots_product_option_exp_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropIndex('products_group_color_idx');
            $table->dropColumn(['group_id', 'color_code', 'color_label']);
        });
    }
};
