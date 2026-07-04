<?php

namespace App\Modules\Core\Controllers\Api\V1;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'ok',
            'service' => 'CreativeSuite ERP API',
            'version' => 'v1',
            'message' => 'Server aktif. Gunakan aplikasi mobile CreativeSuite HR — bukan browser di URL ini.',
            'endpoints' => [
                'login' => '/api/v1/auth/login',
                'health' => '/api/v1/health',
            ],
        ], 'CreativeSuite ERP API is running.');
    }
}