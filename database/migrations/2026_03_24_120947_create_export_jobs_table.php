<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('export_jobs')) {
            Schema::create('export_jobs', function (Blueprint $table) {
                $table->id();

                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('layer_id')->nullable()->constrained('layers')->nullOnDelete();
                $table->foreignId('saved_query_id')->nullable()->constrained('saved_queries')->nullOnDelete();
                $table->foreignId('result_snapshot_id')->nullable()->constrained('result_snapshots')->nullOnDelete();

                $table->string('export_type');   // feature_query, feature_aggregate, feature_statistics, saved_query
                $table->string('format');        // geojson, csv, xlsx, json
                $table->string('status')->default('pending'); // pending, running, completed, failed

                $table->json('request_payload_json')->nullable();
                $table->json('response_meta_json')->nullable();
                $table->json('error_json')->nullable();

                $table->string('disk')->nullable();
                $table->text('file_path')->nullable();
                $table->string('file_name')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('mime_type')->nullable();

                $table->unsignedInteger('result_count')->default(0);
                $table->decimal('duration_ms', 12, 3)->nullable();

                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();

                $table->timestamps();
            });
        }

        try {
            DB::statement('CREATE INDEX IF NOT EXISTS export_jobs_user_id_index ON export_jobs (user_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS export_jobs_layer_id_index ON export_jobs (layer_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS export_jobs_saved_query_id_index ON export_jobs (saved_query_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS export_jobs_result_snapshot_id_index ON export_jobs (result_snapshot_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS export_jobs_status_index ON export_jobs (status)');
            DB::statement('CREATE INDEX IF NOT EXISTS export_jobs_export_type_index ON export_jobs (export_type)');
            DB::statement('CREATE INDEX IF NOT EXISTS export_jobs_format_index ON export_jobs (format)');
        } catch (\Throwable $e) {
            // no-op
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};