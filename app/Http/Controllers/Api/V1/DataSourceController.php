<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DataSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Support\ApiResponse;


class DataSourceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => DataSource::query()->latest()->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:data_sources,code'],
            'name' => ['required', 'string', 'max:255'],
            'driver' => ['required', 'string', 'max:50'],
            'connection_name' => ['required', 'string', 'max:255'],
            'schema_name' => ['required', 'string', 'max:255'],
            'table_name' => ['required', 'string', 'max:255'],
            'geometry_column' => ['required', 'string', 'max:255'],
            'primary_key' => ['required', 'string', 'max:255'],
            'srid' => ['required', 'integer'],
            'geometry_type' => ['nullable', 'string', 'max:100'],
            'title_column' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'metadata_json' => ['nullable', 'array'],
        ]);

        $item = DataSource::create($validated);

        return response()->json([
            'success' => true,
            'data' => $item,
        ], 201);
    }

    public function show(DataSource $dataSource): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $dataSource,
        ]);
    }

    public function update(Request $request, DataSource $dataSource): JsonResponse
    {
        $validated = $request->validate([
            'code' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('data_sources', 'code')->ignore($dataSource->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'driver' => ['sometimes', 'required', 'string', 'max:50'],
            'connection_name' => ['sometimes', 'required', 'string', 'max:255'],
            'schema_name' => ['sometimes', 'required', 'string', 'max:255'],
            'table_name' => ['sometimes', 'required', 'string', 'max:255'],
            'geometry_column' => ['sometimes', 'required', 'string', 'max:255'],
            'primary_key' => ['sometimes', 'required', 'string', 'max:255'],
            'srid' => ['sometimes', 'required', 'integer'],
            'geometry_type' => ['nullable', 'string', 'max:100'],
            'title_column' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'metadata_json' => ['nullable', 'array'],
        ]);

        $dataSource->update($validated);

        return response()->json([
            'success' => true,
            'data' => $dataSource->fresh(),
        ]);
    }

    public function destroy(DataSource $dataSource): JsonResponse
    {
        $dataSource->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data source deleted.',
        ]);
    }

    public function inspect(DataSource $dataSource): JsonResponse
    {
        $connection = $dataSource->connection_name;
        $schema = $dataSource->schema_name;
        $table = $dataSource->table_name;

        $columns = DB::connection($connection)->select(
            <<<SQL
            SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = ?
            AND table_name = ?
            SQL,
            [$schema, $table]
        );

        $geometry = DB::connection($connection)->selectOne(
            <<<SQL
            SELECT f_geometry_column as column_name, type, srid
            FROM geometry_columns
            WHERE f_table_schema = ?
            AND f_table_name = ?
            LIMIT 1
            SQL,
            [$schema, $table]
        );

        return response()->json(ApiResponse::success([
            'columns' => $columns,
            'geometry' => $geometry,
        ]));
    }

}