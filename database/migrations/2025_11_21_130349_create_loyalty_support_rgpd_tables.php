<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // fidélité
        Schema::create('loyalty_point_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('balance')->default(0);
            $table->timestamps();
        });

        Schema::create('loyalty_point_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('loyalty_point_accounts')->cascadeOnDelete();
            $table->enum('type', ['earn', 'spend']);
            $table->integer('amount');
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        // support
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->enum('status', ['open', 'pending', 'closed'])->default('open');
            $table->timestamps();
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->enum('sender_type', ['user', 'admin']);
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->timestamps();
        });

        // Webhooks
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // stripe, paypal
            $table->string('event_type');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_event_id')->constrained('webhook_events')->cascadeOnDelete();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();
        });

        // RGPD
        Schema::create('privacy_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->timestamps();
        });

        Schema::create('privacy_deletions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['requested', 'in_progress', 'done', 'rejected'])->default('requested');
            $table->timestamp('requested_at')->nullable();
            $table->timestamps();
        });

        // AUDIT LOGS
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('privacy_deletions');
        Schema::dropIfExists('privacy_exports');
        Schema::dropIfExists('webhook_failures');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('loyalty_point_movements');
        Schema::dropIfExists('loyalty_point_accounts');
    }
};
