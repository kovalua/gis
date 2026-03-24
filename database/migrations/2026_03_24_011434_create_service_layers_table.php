<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_layers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_id')
                ->constrained('gis_services')
                ->cascadeOnDelete();

            $table->foreignId('layer_id')
                ->constrained('layers')
                ->cascadeOnDelete();

            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['service_id', 'layer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_layers');
    }
};