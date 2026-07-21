<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds human-readable label */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('label', 100)->nullable()->after('name');
        });
    }

    /** Reverts this migration. */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
