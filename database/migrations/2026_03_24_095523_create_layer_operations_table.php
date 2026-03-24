<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('layer_operations')) {
            Schema::create('layer_operations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('layer_id')->constrained('layers')->cascadeOnDelete();
                $table->string('operation_code');
                $table->boolean('is_enabled')->default(true);
                $table->json('config_json')->nullable();
                $table->timestamps();

                $table->unique(['layer_id', 'operation_code']);
            });
        }

        try {
            DB::statement('CREATE INDEX IF NOT EXISTS layer_operations_layer_id_is_enabled_index ON layer_operations (layer_id, is_enabled)');
        } catch (\Throwable $e) {
            // no-op
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('layer_operations');
    }
};