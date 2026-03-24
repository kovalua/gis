<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('saved_query_roles')) {
            Schema::create('saved_query_roles', function (Blueprint $table) {
                $table->id();

                $table->foreignId('saved_query_id')->constrained('saved_queries')->cascadeOnDelete();
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();

                $table->timestamps();

                $table->unique(['saved_query_id', 'role_id']);
            });
        }

        try {
            DB::statement('CREATE INDEX IF NOT EXISTS saved_query_roles_saved_query_id_index ON saved_query_roles (saved_query_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS saved_query_roles_role_id_index ON saved_query_roles (role_id)');
        } catch (\Throwable $e) {
            // no-op
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_query_roles');
    }
};