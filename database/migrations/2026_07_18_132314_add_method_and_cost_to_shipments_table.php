<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds the chosen delivery method and its TTC cost to shipments (standard/express, priced server-side). */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('method', 20)->default('standard')->after('order_id');
            $table->decimal('cost', 8, 2)->default(0)->after('method');
        });
    }

    /** Reverts this migration. */
    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['method', 'cost']);
        });
    }
};
