<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Patches order_items FKs/indexes */
    public function up(): void
    {
        /** 1 - order_items */
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['product_option_id']);
            $table->index(['order_id', 'product_id', 'product_option_id'], 'order_items_order_product_option_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->foreign('product_option_id')->references('id')->on('product_options')->nullOnDelete();
        });

        /** 2 - payments: unique provider + provider_payment_id */
        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['provider', 'provider_payment_id'], 'payments_provider_provider_payment_id_unique');
        });

        /** 3 - shipments: add shipping fields */
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('firstname', 100)->nullable()->after('order_id');
            $table->string('lastname', 100)->nullable()->after('firstname');
            $table->string('zip', 20)->nullable()->after('address');
            $table->string('city', 120)->nullable()->after('zip');
            $table->string('country', 120)->nullable()->after('city');
            $table->string('phone', 30)->nullable()->after('country');
        });
    }

    /** Reverts this migration. */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['firstname', 'lastname', 'zip', 'city', 'country', 'phone']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_provider_provider_payment_id_unique');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['product_option_id']);
            $table->dropIndex('order_items_order_product_option_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_option_id')->references('id')->on('product_options')->nullOnDelete();
        });
    }
};
