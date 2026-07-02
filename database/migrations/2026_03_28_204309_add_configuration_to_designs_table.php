<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds the configuration column to designs. */
    public function up(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            $table->json('configuration')->nullable()->after('metadata');
        });
    }

    /** Reverts this migration. */
    public function down(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            $table->dropColumn('configuration');
        });
    }
};
