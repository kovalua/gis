<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('analytics_executions')) {
            Schema::create('analytics_executions', function (Blueprint $table) {
                $table->id();

                $table->foreignId('saved_query_id')->nullable()->constrained('saved_queries')->nullOnDelete();
                $table->foreignId('layer_id')->nullable()->constrained('layers')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->string('execution_type'); // feature_query, feature_count, feature_aggregate, feature_statistics, ad_hoc
                $table->string('status')->default('success'); // success, failed

                $table->json('request_payload_json')->nullable();
                $table->json('response_meta_json')->nullable();
                $table->json('error_json')->nullable();

                $table->unsignedInteger('result_count')->default(0);
                $table->decimal('duration_ms', 12, 3)->nullable();

                $table->string('ip_address')->nullable();
                $table->text('request_url')->nullable();

                $table->timestamps();
            });
        }

        try {
            DB::statement('CREATE INDEX IF NOT EXISTS analytics_executions_saved_query_id_index ON analytics_executions (saved_query_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS analytics_executions_layer_id_index ON analytics_executions (layer_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS analytics_executions_user_id_index ON analytics_executions (user_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS analytics_executions_execution_type_index ON analytics_executions (execution_type)');
            DB::statement('CREATE INDEX IF NOT EXISTS analytics_executions_status_index ON analytics_executions (status)');
            DB::statement('CREATE INDEX IF NOT EXISTS analytics_executions_created_at_index ON analytics_executions (created_at)');
        } catch (\Throwable $e) {
            // no-op
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_executions');
    }
};