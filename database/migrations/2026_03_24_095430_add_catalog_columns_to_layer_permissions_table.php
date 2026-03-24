<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('layer_permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('layer_permissions', 'can_use_tiles')) {
                $table->boolean('can_use_tiles')->default(false)->after('can_export');
            }

            if (!Schema::hasColumn('layer_permissions', 'can_identify')) {
                $table->boolean('can_identify')->default(false)->after('can_use_tiles');
            }

            if (!Schema::hasColumn('layer_permissions', 'can_attributes')) {
                $table->boolean('can_attributes')->default(false)->after('can_identify');
            }

            if (!Schema::hasColumn('layer_permissions', 'can_aggregate')) {
                $table->boolean('can_aggregate')->default(false)->after('can_attributes');
            }

            if (!Schema::hasColumn('layer_permissions', 'can_statistics')) {
                $table->boolean('can_statistics')->default(false)->after('can_aggregate');
            }

            if (!Schema::hasColumn('layer_permissions', 'can_read_style')) {
                $table->boolean('can_read_style')->default(false)->after('can_statistics');
            }

            if (!Schema::hasColumn('layer_permissions', 'allowed_field_names_json')) {
                $table->json('allowed_field_names_json')->nullable()->after('can_read_style');
            }

            if (!Schema::hasColumn('layer_permissions', 'denied_field_names_json')) {
                $table->json('denied_field_names_json')->nullable()->after('allowed_field_names_json');
            }

            if (!Schema::hasColumn('layer_permissions', 'allowed_style_codes_json')) {
                $table->json('allowed_style_codes_json')->nullable()->after('denied_field_names_json');
            }
        });

        try {
            DB::statement('CREATE INDEX IF NOT EXISTS layer_permissions_can_use_tiles_index ON layer_permissions (can_use_tiles)');
            DB::statement('CREATE INDEX IF NOT EXISTS layer_permissions_can_read_style_index ON layer_permissions (can_read_style)');
        } catch (\Throwable $e) {
            // no-op
        }
    }

    public function down(): void
    {
        Schema::table('layer_permissions', function (Blueprint $table) {
            if (Schema::hasColumn('layer_permissions', 'allowed_style_codes_json')) {
                $table->dropColumn('allowed_style_codes_json');
            }

            if (Schema::hasColumn('layer_permissions', 'denied_field_names_json')) {
                $table->dropColumn('denied_field_names_json');
            }

            if (Schema::hasColumn('layer_permissions', 'allowed_field_names_json')) {
                $table->dropColumn('allowed_field_names_json');
            }

            if (Schema::hasColumn('layer_permissions', 'can_read_style')) {
                $table->dropColumn('can_read_style');
            }

            if (Schema::hasColumn('layer_permissions', 'can_statistics')) {
                $table->dropColumn('can_statistics');
            }

            if (Schema::hasColumn('layer_permissions', 'can_aggregate')) {
                $table->dropColumn('can_aggregate');
            }

            if (Schema::hasColumn('layer_permissions', 'can_attributes')) {
                $table->dropColumn('can_attributes');
            }

            if (Schema::hasColumn('layer_permissions', 'can_identify')) {
                $table->dropColumn('can_identify');
            }

            if (Schema::hasColumn('layer_permissions', 'can_use_tiles')) {
                $table->dropColumn('can_use_tiles');
            }
        });
    }
};