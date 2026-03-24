<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('layer_fields')) {
            Schema::create('layer_fields', function (Blueprint $table) {
                $table->id();
                $table->foreignId('layer_id')->constrained('layers')->cascadeOnDelete();

                $table->string('name');
                $table->string('title');
                $table->string('data_type');
                $table->string('db_column')->nullable();

                $table->boolean('is_nullable')->default(true);
                $table->boolean('is_visible')->default(true);
                $table->boolean('is_filterable')->default(true);
                $table->boolean('is_sortable')->default(true);
                $table->boolean('is_searchable')->default(false);
                $table->boolean('is_editable')->default(false);

                $table->boolean('visible_in_list')->default(true);
                $table->boolean('visible_in_popup')->default(true);
                $table->boolean('visible_in_form')->default(false);

                $table->json('operators_json')->nullable();
                $table->json('domain_json')->nullable();
                $table->string('default_value')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('metadata_json')->nullable();

                $table->timestamps();

                $table->unique(['layer_id', 'name']);
            });
        }

        try {
            DB::statement('CREATE INDEX IF NOT EXISTS layer_fields_layer_id_sort_order_index ON layer_fields (layer_id, sort_order)');
        } catch (\Throwable $e) {
            // no-op
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('layer_fields');
    }
};