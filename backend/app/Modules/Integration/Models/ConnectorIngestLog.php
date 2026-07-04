<?php

namespace App\Modules\Integration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectorIngestLog extends Model
{
    public $timestamps = false;

    protected $table = 'cs_int_connector_ingest_logs';

    protected $fillable = [
        'connector_id',
        'processed',
        'errors',
        'payload_hash',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(ConnectorConfig::class, 'connector_id');
    }
}