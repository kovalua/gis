<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('data_source_id')->constrained('data_sources')->cascadeOnDelete();
            $table->string('layer_type')->default('vector');
            $table->string('geometry_type')->nullable();
            $table->string('title_field')->nullable();
            $table->string('description_field')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_queryable')->default(true);
            $table->boolean('is_editable')->default(false);
            $table->boolean('is_exportable')->default(false);
            $table->integer('min_zoom')->default(0);
            $table->integer('max_zoom')->default(22);
            $table->json('filter_definition_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layers');
    }
};