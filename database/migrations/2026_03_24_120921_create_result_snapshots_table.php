<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('result_snapshots')) {
            Schema::create('result_snapshots', function (Blueprint $table) {
                $table->id();

                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('layer_id')->nullable()->constrained('layers')->nullOnDelete();
                $table->foreignId('saved_query_id')->nullable()->constrained('saved_queries')->nullOnDelete();

                $table->string('snapshot_type'); // feature_query, feature_count, feature_aggregate, feature_statistics, saved_query
                $table->string('name');
                $table->text('description')->nullable();

                $table->json('request_payload_json')->nullable();
                $table->json('result_meta_json')->nullable();
                $table->json('preview_json')->nullable();

                $table->unsignedInteger('result_count')->default(0);
                $table->boolean('is_public')->default(false);

                $table->timestamps();
            });
        }

        try {
            DB::statement('CREATE INDEX IF NOT EXISTS result_snapshots_user_id_index ON result_snapshots (user_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS result_snapshots_layer_id_index ON result_snapshots (layer_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS result_snapshots_saved_query_id_index ON result_snapshots (saved_query_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS result_snapshots_snapshot_type_index ON result_snapshots (snapshot_type)');
            DB::statement('CREATE INDEX IF NOT EXISTS result_snapshots_is_public_index ON result_snapshots (is_public)');
        } catch (\Throwable $e) {
            // no-op
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('result_snapshots');
    }
};