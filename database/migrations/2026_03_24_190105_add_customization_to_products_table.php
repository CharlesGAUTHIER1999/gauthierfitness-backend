<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds the is_customizable, customization_mode, and allow_* customization flag columns to products. */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_customizable')->default(false);
            $table->string('customization_mode')->nullable();
            $table->boolean('allow_text_customization')->default(false);
            $table->boolean('allow_image_upload')->default(false);
            $table->boolean('allow_ai_generation')->default(false);
        });
    }

    /** Reverts this migration. */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'is_customizable',
                'customization_mode',
                'allow_text_customization',
                'allow_image_upload',
                'allow_ai_generation',
            ]);
        });
    }
};
