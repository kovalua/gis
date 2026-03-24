<?php

namespace App\Support;

class ApiResponse
{
    public static function success($data = null, array $meta = [], ?string $message = null): array
    {
        return [
            'success' => true,
            'data' => $data,
            'meta' => $meta,
            'message' => $message,
        ];
    }

    public static function error(string $code, string $message, array $details = []): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ];
    }
}