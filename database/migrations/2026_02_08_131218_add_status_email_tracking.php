<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds paid/shipped/delivered/canceled email-sent timestamp columns to orders. */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('paid_email_sent_at')->nullable()->after('order_status');
            $table->timestamp('shipped_email_sent_at')->nullable()->after('paid_email_sent_at');
            $table->timestamp('delivered_email_sent_at')->nullable()->after('shipped_email_sent_at');
            $table->timestamp('canceled_email_sent_at')->nullable()->after('delivered_email_sent_at');
        });
    }

    /** Reverts this migration. */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'paid_email_sent_at',
                'shipped_email_sent_at',
                'delivered_email_sent_at',
                'canceled_email_sent_at',
            ]);
        });
    }
};
