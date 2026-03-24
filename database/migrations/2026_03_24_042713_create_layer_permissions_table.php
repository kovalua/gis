<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layer_permissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('layer_id')->constrained('layers')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();

            $table->boolean('can_view')->default(false);
            $table->boolean('can_query')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_export')->default(false);

            $table->timestamps();

            $table->unique(['layer_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layer_permissions');
    }
};