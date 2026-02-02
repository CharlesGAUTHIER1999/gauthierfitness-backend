<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * 1) order_items : NE PAS cascade delete sur product / option
         *    + index composite utile
         */
        Schema::table('order_items', function (Blueprint $table) {
            // Drop FK existantes (noms par convention Laravel)
            // Si une des colonnes n'existe pas (selon ton historique), commente la ligne correspondante.
            $table->dropForeign(['product_id']);
            // product_option_id vient de ta migration 2026-02-02, donc FK existe
            $table->dropForeign(['product_option_id']);

            // Ajoute index composite (si tu veux éviter doublon, très utile pour requêtes)
            // Attention : s'il existe déjà, il faudra dropIndex avant.
            $table->index(['order_id', 'product_id', 'product_option_id'], 'order_items_order_product_option_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Recrée FK product_id : restrict (protège l'historique)
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->restrictOnDelete();

            // Recrée FK product_option_id : nullOnDelete OU restrictOnDelete
            // => je recommande restrictOnDelete si tu veux empêcher la suppression d’options déjà commandées.
            $table->foreign('product_option_id')
                ->references('id')
                ->on('product_options')
                ->nullOnDelete();
        });

        /**
         * 2) payments : unique provider + provider_payment_id
         */
        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['provider', 'provider_payment_id'], 'payments_provider_provider_payment_id_unique');
        });

        /**
         * 3) shipments : ajouter champs shipping
         */
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('firstname', 100)->nullable()->after('order_id');
            $table->string('lastname', 100)->nullable()->after('firstname');
            $table->string('zip', 20)->nullable()->after('address');
            $table->string('city', 120)->nullable()->after('zip');
            $table->string('country', 120)->nullable()->after('city');
            $table->string('phone', 30)->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['firstname', 'lastname', 'zip', 'city', 'country', 'phone']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_provider_provider_payment_id_unique');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Drop constraints recréées
            $table->dropForeign(['product_id']);
            $table->dropForeign(['product_option_id']);
            $table->dropIndex('order_items_order_product_option_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Remet le comportement initial si tu veux (cascade)
            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->foreign('product_option_id')
                ->references('id')
                ->on('product_options')
                ->nullOnDelete();
        });
    }
};
