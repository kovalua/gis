<?php

namespace App\Services\Gis;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GeometryService
{
    public function buffer(array $geometry, int $srid, float $distance): array
    {
        $geojson = json_encode($geometry);

        $row = DB::selectOne(
            <<<SQL
            SELECT ST_AsGeoJSON(
                ST_Buffer(
                    ST_SetSRID(ST_GeomFromGeoJSON(?), ?),
                    ?
                )
            ) AS geometry
            SQL,
            [$geojson, $srid, $distance]
        );

        return $this->decodeGeometryResult($row->geometry ?? null);
    }

    public function bufferMeters(array $geometry, int $srid, float $distance): array
    {
        $geojson = json_encode($geometry);

        $row = DB::selectOne(
            <<<SQL
            SELECT ST_AsGeoJSON(
                ST_Buffer(
                    ST_Transform(
                        ST_SetSRID(ST_GeomFromGeoJSON(?), ?),
                        3857
                    ),
                    ?
                )
            ) AS geometry
            SQL,
            [$geojson, $srid, $distance]
        );

        return $this->decodeGeometryResult($row->geometry ?? null);
    }



    public function intersection(array $geometryA, array $geometryB, int $srid): array
    {
        $geojsonA = json_encode($geometryA);
        $geojsonB = json_encode($geometryB);

        $row = DB::selectOne(
            <<<SQL
            SELECT ST_AsGeoJSON(
                ST_Intersection(
                    ST_SetSRID(ST_GeomFromGeoJSON(?), ?),
                    ST_SetSRID(ST_GeomFromGeoJSON(?), ?)
                )
            ) AS geometry
            SQL,
            [$geojsonA, $srid, $geojsonB, $srid]
        );

        return $this->decodeGeometryResult($row->geometry ?? null);
    }

    public function area(array $geometry, int $srid): float
    {
        $geojson = json_encode($geometry);

        $row = DB::selectOne(
            <<<SQL
            SELECT ST_Area(
                ST_Transform(
                    ST_SetSRID(ST_GeomFromGeoJSON(?), ?),
                    3857
                )
            ) AS area
            SQL,
            [$geojson, $srid]
        );

        return (float) ($row->area ?? 0);
    }

    protected function decodeGeometryResult(?string $geometry): array
    {
        if (!$geometry) {
            throw new InvalidArgumentException('Geometry operation returned empty result.');
        }

        $decoded = json_decode($geometry, true);

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Failed to decode geometry result.');
        }

        return $decoded;
    }

    public function centroid(array $geometry, int $srid): array
    {
        $geojson = json_encode($geometry);

        $row = DB::selectOne(
            "SELECT ST_AsGeoJSON(ST_Centroid(ST_SetSRID(ST_GeomFromGeoJSON(?), ?))) as geometry",
            [$geojson, $srid]
        );

        return $this->decodeGeometryResult($row->geometry);
    }


    public function validate(array $geometry, int $srid): array
    {
        $geojson = json_encode($geometry);

        $row = DB::selectOne(
            "SELECT ST_IsValid(ST_SetSRID(ST_GeomFromGeoJSON(?), ?)) as valid,
                    ST_IsValidReason(ST_SetSRID(ST_GeomFromGeoJSON(?), ?)) as reason",
            [$geojson, $srid, $geojson, $srid]
        );

        return [
            'valid' => $row->valid,
            'reason' => $row->reason,
        ];
    }


    public function validateOrFail(array $geometry, int $srid): void
    {
        $result = $this->validate($geometry, $srid);

        $validRaw = $result['valid'] ?? false;
        $isValid = filter_var($validRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($isValid !== true) {
            $reason = $result['reason'] ?? 'Unknown geometry validation error.';
            throw new \InvalidArgumentException('Invalid geometry: ' . $reason);
        }
    }

}