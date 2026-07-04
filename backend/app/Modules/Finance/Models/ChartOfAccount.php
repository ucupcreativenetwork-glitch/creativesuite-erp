<?php

namespace App\Modules\Finance\Models;

use App\Modules\Finance\Enums\AccountCategory;
use App\Modules\Finance\Enums\AccountType;
use App\Modules\Finance\Enums\NormalBalance;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use BelongsToCompany, BelongsToTenant, SoftDeletes;

    protected $table = 'cs_fin_chart_of_accounts';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'public_id',
        'code',
        'name',
        'category',
        'account_type',
        'parent_id',
        'normal_balance',
        'is_postable',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'category' => AccountCategory::class,
            'account_type' => AccountType::class,
            'normal_balance' => NormalBalance::class,
            'is_postable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}