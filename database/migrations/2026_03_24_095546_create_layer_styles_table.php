<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('layer_styles')) {
            Schema::create('layer_styles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('layer_id')->constrained('layers')->cascadeOnDelete();

                $table->string('code');
                $table->string('name');
                $table->string('style_type')->default('simple');

                $table->json('style_json')->nullable();
                $table->json('legend_json')->nullable();

                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);

                $table->timestamps();

                $table->unique(['layer_id', 'code']);
            });
        }

        try {
            DB::statement('CREATE INDEX IF NOT EXISTS layer_styles_layer_id_is_active_index ON layer_styles (layer_id, is_active)');
            DB::statement('CREATE INDEX IF NOT EXISTS layer_styles_layer_id_sort_order_index ON layer_styles (layer_id, sort_order)');
        } catch (\Throwable $e) {
            // no-op
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('layer_styles');
    }
};