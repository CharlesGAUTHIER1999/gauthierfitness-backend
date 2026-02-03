<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->string('provider_event_id', 191)->nullable()->after('provider');
            $table->unique(['provider', 'provider_event_id'], 'webhook_events_provider_event_unique');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropUnique('webhook_events_provider_event_unique');
            $table->dropColumn('provider_event_id');
        });
    }
};
