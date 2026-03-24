<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('driver')->default('postgis');
            $table->string('connection_name')->default('pgsql');
            $table->string('schema_name')->default('public');
            $table->string('table_name');
            $table->string('geometry_column')->default('geom');
            $table->string('primary_key')->default('id');
            $table->integer('srid')->default(4326);
            $table->string('geometry_type')->nullable();
            $table->string('title_column')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_sources');
    }
};