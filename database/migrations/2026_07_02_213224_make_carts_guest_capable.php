<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allows a cart to belong to an anonymous guest (via a token) instead of a user.
     */
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            // MySQL requires dropping the FK before dropping the unique index backing it.
            $table->dropForeign(['user_id']);
            $table->dropUnique('carts_user_id_unique');
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('guest_token')->nullable()->unique()->after('user_id');
        });
    }

    /**
     * Reverts this migration.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropUnique('carts_guest_token_unique');
            $table->dropColumn('guest_token');
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
