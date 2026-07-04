<?php

namespace App\Modules\Finance\Services;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Models\ChartOfAccount;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Str;

class CoaService
{
    public function list(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'fin.coa.read');

        $query = ChartOfAccount::query()->orderBy('code');

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        return $query->get();
    }

    public function tree(User $user): array
    {
        $accounts = $this->list($user, ['is_active' => true]);
        $indexed = $accounts->keyBy('id');
        $tree = [];

        foreach ($accounts as $account) {
            if ($account->parent_id === null) {
                $tree[] = $this->buildNode($account, $indexed);
            }
        }

        return $tree;
    }

    protected function buildNode(ChartOfAccount $account, $indexed): array
    {
        $children = $indexed->filter(fn ($a) => $a->parent_id === $account->id)
            ->map(fn ($child) => $this->buildNode($child, $indexed))
            ->values()
            ->all();

        return [
            'id' => $account->id,
            'public_id' => $account->public_id,
            'code' => $account->code,
            'name' => $account->name,
            'category' => $account->category,
            'account_type' => $account->account_type,
            'normal_balance' => $account->normal_balance,
            'is_postable' => $account->is_postable,
            'children' => $children,
        ];
    }

    public function create(User $user, array $data): ChartOfAccount
    {
        $this->assertPermission($user, 'fin.coa.create');

        return ChartOfAccount::create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'public_id' => (string) Str::uuid(),
            'code' => $data['code'],
            'name' => $data['name'],
            'category' => $data['category'],
            'account_type' => $data['account_type'],
            'parent_id' => $data['parent_id'] ?? null,
            'normal_balance' => $data['normal_balance'],
            'is_postable' => $data['is_postable'] ?? true,
            'is_active' => true,
            'description' => $data['description'] ?? null,
        ]);
    }

    public function update(User $user, string $publicId, array $data): ChartOfAccount
    {
        $this->assertPermission($user, 'fin.coa.update');

        $account = ChartOfAccount::query()->where('public_id', $publicId)->firstOrFail();
        $account->update(collect($data)->only([
            'name', 'description', 'is_active', 'is_postable',
        ])->filter()->all());

        return $account->fresh();
    }

    protected function assertPermission(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }
    }
}