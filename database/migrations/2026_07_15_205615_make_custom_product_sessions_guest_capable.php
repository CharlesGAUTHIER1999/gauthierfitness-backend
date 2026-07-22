<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allows a customization session to belong to an anonymous guest (via a token)
     */
    public function up(): void
    {
        Schema::table('custom_product_sessions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('guest_token')->nullable()->after('user_id');
        });
    }

    /**
     * Reverts this migration.
     */
    public function down(): void
    {
        Schema::table('custom_product_sessions', function (Blueprint $table) {
            $table->dropColumn('guest_token');
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
