<?php

namespace App\Modules\Integration\Models;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Modules\Integration\Enums\ConnectorType;
use App\Support\Tenant\BelongsToCompany;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConnectorConfig extends Model
{
    use BelongsToCompany, BelongsToTenant;

    protected $table = 'cs_int_connector_configs';

    protected $fillable = [
        'public_id', 'tenant_id', 'company_id', 'connector_type', 'name',
        'ingest_token', 'employee_match_field', 'settings', 'is_active',
        'last_ingest_at', 'last_processed_count', 'last_error_count', 'created_by',
    ];

    protected $hidden = ['ingest_token'];

    protected function casts(): array
    {
        return [
            'connector_type' => ConnectorType::class,
            'settings' => 'array',
            'is_active' => 'boolean',
            'last_ingest_at' => 'datetime',
            'last_processed_count' => 'integer',
            'last_error_count' => 'integer',
        ];
    }

    public function ingestLogs(): HasMany
    {
        return $this->hasMany(ConnectorIngestLog::class, 'connector_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}