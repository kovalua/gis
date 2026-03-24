<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('saved_queries')) {
            Schema::create('saved_queries', function (Blueprint $table) {
                $table->id();

                $table->string('code')->unique();
                $table->string('name');
                $table->text('description')->nullable();

                $table->foreignId('layer_id')->constrained('layers')->cascadeOnDelete();
                $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->string('query_type');
                $table->string('visibility')->default('private'); // private, role, public
                $table->boolean('is_active')->default(true);

                $table->json('payload_json');
                $table->json('metadata_json')->nullable();

                $table->timestamps();
            });
        }

        try {
            DB::statement('CREATE INDEX IF NOT EXISTS saved_queries_layer_id_index ON saved_queries (layer_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS saved_queries_owner_user_id_index ON saved_queries (owner_user_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS saved_queries_query_type_index ON saved_queries (query_type)');
            DB::statement('CREATE INDEX IF NOT EXISTS saved_queries_visibility_index ON saved_queries (visibility)');
            DB::statement('CREATE INDEX IF NOT EXISTS saved_queries_is_active_index ON saved_queries (is_active)');
        } catch (\Throwable $e) {
            // no-op
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_queries');
    }
};