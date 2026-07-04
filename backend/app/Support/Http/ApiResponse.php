<?php

namespace App\Support\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data instanceof JsonResource ? $data->resolve() : $data,
            'meta' => array_merge([
                'request_id' => request()->header('X-Request-ID', (string) str()->uuid()),
                'timestamp' => now()->toIso8601String(),
            ], $meta),
        ];

        if ($data instanceof JsonResource && $data->resource instanceof LengthAwarePaginator) {
            $paginator = $data->resource;
            $payload['meta']['pagination'] = [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ];
        }

        return response()->json($payload, $status);
    }

    public static function error(
        string $message,
        int $status = 400,
        ?string $errorCode = null,
        mixed $errors = null,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'meta' => [
                'request_id' => request()->header('X-Request-ID', (string) str()->uuid()),
                'error_code' => $errorCode,
                'timestamp' => now()->toIso8601String(),
            ],
        ], $status);
    }
}