<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('layers', function (Blueprint $table) {
            if (!Schema::hasColumn('layers', 'description')) {
                $table->text('description')->nullable()->after('name');
            }

            if (!Schema::hasColumn('layers', 'group_code')) {
                $table->string('group_code')->nullable()->after('description_field');
            }

            if (!Schema::hasColumn('layers', 'default_visibility')) {
                $table->boolean('default_visibility')->default(true)->after('max_zoom');
            }

            if (!Schema::hasColumn('layers', 'catalog_order')) {
                $table->unsignedInteger('catalog_order')->default(0)->after('default_visibility');
            }

            if (!Schema::hasColumn('layers', 'is_public')) {
                $table->boolean('is_public')->default(false)->after('is_active');
            }
        });

        try {
            DB::statement('CREATE INDEX IF NOT EXISTS layers_group_code_index ON layers (group_code)');
            DB::statement('CREATE INDEX IF NOT EXISTS layers_catalog_order_index ON layers (catalog_order)');
            DB::statement('CREATE INDEX IF NOT EXISTS layers_is_public_index ON layers (is_public)');
        } catch (\Throwable $e) {
            // no-op
        }
    }

    public function down(): void
    {
        Schema::table('layers', function (Blueprint $table) {
            if (Schema::hasColumn('layers', 'is_public')) {
                $table->dropColumn('is_public');
            }

            if (Schema::hasColumn('layers', 'catalog_order')) {
                $table->dropColumn('catalog_order');
            }

            if (Schema::hasColumn('layers', 'default_visibility')) {
                $table->dropColumn('default_visibility');
            }

            if (Schema::hasColumn('layers', 'group_code')) {
                $table->dropColumn('group_code');
            }

            if (Schema::hasColumn('layers', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};