<?php

namespace App\Modules\Iam\Models;

use App\Modules\Core\Models\User;
use App\Support\Tenant\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IamNotification extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'cs_core_notifications';

    protected $fillable = [
        'tenant_id', 'user_id', 'channel', 'type', 'title', 'body', 'payload', 'read_at', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}