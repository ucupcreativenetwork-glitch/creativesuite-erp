<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Support\Exceptions\ApiException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CompanyService
{
    public function show(User $user, string $publicId): Company
    {
        $this->assertPermission($user, 'core.company.read');

        return Company::query()
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    public function update(User $user, string $publicId, array $data): Company
    {
        $this->assertPermission($user, 'core.company.update');

        $company = Company::query()
            ->where('public_id', $publicId)
            ->firstOrFail();

        if ($company->id !== $user->default_company_id) {
            throw new ApiException('You can only update your default company.', 403, 'FORBIDDEN');
        }

        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = array_replace_recursive($company->settings ?? [], $data['settings']);
        }

        $company->update($data);

        return $company->fresh();
    }

    public function uploadLogo(User $user, string $publicId, UploadedFile $file): Company
    {
        $this->assertPermission($user, 'core.company.update');

        $company = Company::query()
            ->where('public_id', $publicId)
            ->firstOrFail();

        if ($company->id !== $user->default_company_id) {
            throw new ApiException('You can only update your default company.', 403, 'FORBIDDEN');
        }

        $this->deleteStoredLogo($company->logo_url);

        $path = $file->store("company-logos/{$company->tenant_id}", 'public');
        $company->update(['logo_url' => $path]);

        return $company->fresh();
    }

    protected function deleteStoredLogo(?string $logoUrl): void
    {
        if (! $logoUrl) {
            return;
        }

        $relativePath = $logoUrl;

        if (str_contains($logoUrl, '/storage/')) {
            $relativePath = ltrim(str_replace('/storage/', '', parse_url($logoUrl, PHP_URL_PATH) ?: $logoUrl), '/');
        } elseif (str_starts_with($logoUrl, 'http://') || str_starts_with($logoUrl, 'https://')) {
            $relativePath = ltrim(str_replace('/storage/', '', parse_url($logoUrl, PHP_URL_PATH) ?: ''), '/');
        }

        if ($relativePath !== '' && ! str_starts_with($relativePath, 'http')) {
            Storage::disk('public')->delete($relativePath);
        }
    }

    protected function assertPermission(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }
    }
}