<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Concerns\ValidatesTenantRelations;
use App\Modules\Business\Enums\AccountStatus;
use App\Modules\Business\Models\CrmAccount;
use App\Modules\Business\Models\CrmContact;
use App\Modules\Core\Models\User;
use App\Support\Business\ChecksPermissions;
use App\Support\Business\GeneratesDocumentNumber;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CrmAccountService
{
    use ChecksPermissions, GeneratesDocumentNumber, ValidatesTenantRelations;

    public function list(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'crm.account.read');

        $query = CrmAccount::query()->withCount('contacts')->orderBy('name');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['account_type'])) {
            $query->where('account_type', $filters['account_type']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('account_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function show(User $user, string $publicId): CrmAccount
    {
        $this->assertPermission($user, 'crm.account.read');

        return CrmAccount::query()
            ->where('public_id', $publicId)
            ->with('contacts')
            ->firstOrFail();
    }

    public function create(User $user, array $data): CrmAccount
    {
        $this->assertPermission($user, 'crm.account.create');

        $prefix = match ($data['account_type']) {
            'VENDOR' => 'VND-',
            'BOTH' => 'ACC-',
            default => 'CST-',
        };
        $accountCode = $data['account_code']
            ?? $this->generateNumber(
                new CrmAccount,
                $user->tenant_id,
                $user->default_company_id,
                $prefix,
                'account_code',
            );

        return CrmAccount::create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'public_id' => (string) Str::uuid(),
            'account_code' => $accountCode,
            'name' => $data['name'],
            'account_type' => $data['account_type'],
            'status' => $data['status'] ?? AccountStatus::Active->value,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'whatsapp' => $data['whatsapp'] ?? null,
            'npwp' => $data['npwp'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'credit_limit' => $data['credit_limit'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'created_by' => $user->id,
        ]);
    }

    public function update(User $user, string $publicId, array $data): CrmAccount
    {
        $this->assertPermission($user, 'crm.account.update');

        $account = CrmAccount::query()->where('public_id', $publicId)->firstOrFail();
        $account->update(array_filter($data, fn ($v) => $v !== null));

        return $account->fresh(['contacts']);
    }

    public function delete(User $user, string $publicId): void
    {
        $this->assertPermission($user, 'crm.account.delete');

        $account = CrmAccount::query()->where('public_id', $publicId)->firstOrFail();
        $account->delete();
    }

    public function listContacts(User $user, string $accountPublicId)
    {
        $this->assertPermission($user, 'crm.contact.read');

        $account = CrmAccount::query()->where('public_id', $accountPublicId)->firstOrFail();

        return CrmContact::query()
            ->where('account_id', $account->id)
            ->orderByDesc('is_primary')
            ->orderBy('full_name')
            ->paginate(request()->input('per_page', 25));
    }

    public function createContact(User $user, string $accountPublicId, array $data): CrmContact
    {
        $this->assertPermission($user, 'crm.contact.create');

        $account = CrmAccount::query()->where('public_id', $accountPublicId)->firstOrFail();

        return DB::transaction(function () use ($user, $account, $data) {
            if ($data['is_primary'] ?? false) {
                CrmContact::query()
                    ->where('account_id', $account->id)
                    ->update(['is_primary' => false]);
            }

            return CrmContact::create([
                'tenant_id' => $user->tenant_id,
                'account_id' => $account->id,
                'public_id' => (string) Str::uuid(),
                'full_name' => $data['full_name'],
                'job_title' => $data['job_title'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'is_primary' => $data['is_primary'] ?? false,
            ]);
        });
    }
}