<?php

namespace App\Modules\Core\Models;

use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'cs_core_branches';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'code',
        'name',
        'address',
        'city',
        'phone',
        'is_head_office',
        'is_active',
        'attendance_geofence_enabled',
        'attendance_latitude',
        'attendance_longitude',
        'attendance_geofence_radius_m',
    ];

    protected function casts(): array
    {
        return [
            'is_head_office' => 'boolean',
            'is_active' => 'boolean',
            'attendance_geofence_enabled' => 'boolean',
            'attendance_latitude' => 'decimal:7',
            'attendance_longitude' => 'decimal:7',
            'attendance_geofence_radius_m' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}