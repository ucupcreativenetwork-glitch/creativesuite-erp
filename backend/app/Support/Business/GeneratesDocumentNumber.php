<?php

namespace App\Support\Business;

use Illuminate\Database\Eloquent\Model;

trait GeneratesDocumentNumber
{
    protected function generateNumber(
        Model $model,
        int $tenantId,
        int $companyId,
        string $prefix,
        string $column = 'invoice_number',
    ): string {
        $prefix .= now()->format('Ym').'-';

        $last = $model::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where($column, 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value($column);

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}