<?php

namespace App\Modules\Core\Controllers\Api\V1;

use App\Modules\Auth\Resources\CompanyResource;
use App\Modules\Core\Requests\UpdateCompanyRequest;
use App\Modules\Core\Requests\UploadCompanyLogoRequest;
use App\Modules\Core\Services\CompanyService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CompanyController extends Controller
{
    public function __construct(protected CompanyService $companyService) {}

    public function show(string $publicId): JsonResponse
    {
        $user = auth('api')->user();
        $company = $this->companyService->show($user, $publicId);

        return ApiResponse::success(new CompanyResource($company));
    }

    public function update(UpdateCompanyRequest $request, string $publicId): JsonResponse
    {
        $user = auth('api')->user();
        $company = $this->companyService->update($user, $publicId, $request->validated());

        return ApiResponse::success(new CompanyResource($company), 'Company updated.');
    }

    public function uploadLogo(UploadCompanyLogoRequest $request, string $publicId): JsonResponse
    {
        $user = auth('api')->user();
        $company = $this->companyService->uploadLogo($user, $publicId, $request->file('logo'));

        return ApiResponse::success(new CompanyResource($company), 'Company logo uploaded.');
    }
}