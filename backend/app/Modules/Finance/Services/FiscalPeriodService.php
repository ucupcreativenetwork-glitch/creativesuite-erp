<?php

namespace App\Modules\Finance\Services;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\FiscalPeriodStatus;
use App\Modules\Finance\Models\FiscalPeriod;
use App\Support\Exceptions\ApiException;
use Carbon\Carbon;

class FiscalPeriodService
{
    public function listForUser(User $user, ?int $year = null)
    {
        $this->assertPermission($user, 'fin.fiscal_period.read');

        return $this->list($user->default_company_id, $year);
    }

    public function resolveForDate(int $companyId, Carbon $date): FiscalPeriod
    {
        $period = FiscalPeriod::query()
            ->where('company_id', $companyId)
            ->where('year', $date->year)
            ->where('month', $date->month)
            ->first();

        if (! $period) {
            throw new ApiException('Fiscal period not found for date.', 422, 'FISCAL_PERIOD_NOT_FOUND');
        }

        return $period;
    }

    public function assertOpen(FiscalPeriod $period): void
    {
        if ($period->status !== FiscalPeriodStatus::Open) {
            throw new ApiException('Fiscal period is closed or locked.', 422, 'FISCAL_PERIOD_CLOSED');
        }
    }

    public function list(int $companyId, ?int $year = null)
    {
        $query = FiscalPeriod::query()->where('company_id', $companyId)->orderBy('year')->orderBy('month');

        if ($year) {
            $query->where('year', $year);
        }

        return $query->get();
    }

    public function close(User $user, int $year, int $month): FiscalPeriod
    {
        $this->assertPermission($user, 'fin.fiscal_period.close');

        $period = $this->resolvePeriod($user->default_company_id, $year, $month);

        if ($period->status !== FiscalPeriodStatus::Open) {
            throw new ApiException('Only open fiscal periods can be closed.', 422, 'FISCAL_PERIOD_NOT_OPEN');
        }

        $period->update([
            'status' => FiscalPeriodStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $user->id,
        ]);

        return $period->fresh();
    }

    public function lock(User $user, int $year, int $month): FiscalPeriod
    {
        $this->assertPermission($user, 'fin.fiscal_period.lock');

        $period = $this->resolvePeriod($user->default_company_id, $year, $month);

        if ($period->status === FiscalPeriodStatus::Locked) {
            throw new ApiException('Fiscal period is already locked.', 422, 'FISCAL_PERIOD_ALREADY_LOCKED');
        }

        if ($period->status === FiscalPeriodStatus::Open) {
            throw new ApiException('Close the fiscal period before locking.', 422, 'FISCAL_PERIOD_NOT_CLOSED');
        }

        $period->update([
            'status' => FiscalPeriodStatus::Locked,
            'closed_at' => $period->closed_at ?? now(),
            'closed_by' => $period->closed_by ?? $user->id,
        ]);

        return $period->fresh();
    }

    public function reopen(User $user, int $year, int $month): FiscalPeriod
    {
        $this->assertPermission($user, 'fin.fiscal_period.close');

        $period = $this->resolvePeriod($user->default_company_id, $year, $month);

        if ($period->status === FiscalPeriodStatus::Open) {
            throw new ApiException('Fiscal period is already open.', 422, 'FISCAL_PERIOD_ALREADY_OPEN');
        }

        $period->update([
            'status' => FiscalPeriodStatus::Open,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return $period->fresh();
    }

    protected function resolvePeriod(int $companyId, int $year, int $month): FiscalPeriod
    {
        $period = FiscalPeriod::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if (! $period) {
            throw new ApiException('Fiscal period not found.', 404, 'FISCAL_PERIOD_NOT_FOUND');
        }

        return $period;
    }

    protected function assertPermission(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }
    }
}