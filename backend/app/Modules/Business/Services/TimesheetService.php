<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Models\Employee;
use App\Modules\Business\Models\Project;
use App\Modules\Business\Models\TimeEntry;
use App\Modules\Core\Models\User;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Str;

class TimesheetService
{
    public function list(User $user, string $projectPublicId, array $filters = [])
    {
        $this->assertPermission($user, 'prj.timesheet.read');
        $project = $this->findProject($user, $projectPublicId);

        $query = TimeEntry::query()
            ->where('project_id', $project->id)
            ->with('employee')
            ->orderByDesc('entry_date');

        if (! empty($filters['from_date'])) {
            $query->where('entry_date', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->where('entry_date', '<=', $filters['to_date']);
        }

        return $query->paginate($filters['per_page'] ?? 50);
    }

    public function create(User $user, string $projectPublicId, array $data): TimeEntry
    {
        $this->assertPermission($user, 'prj.timesheet.create');
        $project = $this->findProject($user, $projectPublicId);

        $hourlyCost = $data['hourly_cost'] ?? $this->resolveHourlyCost($data['employee_id'] ?? null);

        return TimeEntry::create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'public_id' => (string) Str::uuid(),
            'project_id' => $project->id,
            'employee_id' => $data['employee_id'] ?? null,
            'user_id' => $user->id,
            'entry_date' => $data['entry_date'],
            'hours' => $data['hours'],
            'hourly_cost' => $hourlyCost,
            'is_billable' => $data['is_billable'] ?? true,
            'description' => $data['description'] ?? null,
            'created_by' => $user->id,
        ])->load('employee');
    }

    public function update(User $user, string $projectPublicId, string $entryPublicId, array $data): TimeEntry
    {
        $this->assertPermission($user, 'prj.timesheet.update');
        $project = $this->findProject($user, $projectPublicId);

        $entry = TimeEntry::query()
            ->where('project_id', $project->id)
            ->where('public_id', $entryPublicId)
            ->firstOrFail();

        $entry->update(collect($data)->only([
            'employee_id', 'entry_date', 'hours', 'hourly_cost', 'is_billable', 'description',
        ])->filter(fn ($v) => $v !== null)->all());

        return $entry->fresh(['employee']);
    }

    public function delete(User $user, string $projectPublicId, string $entryPublicId): void
    {
        $this->assertPermission($user, 'prj.timesheet.delete');
        $project = $this->findProject($user, $projectPublicId);

        $entry = TimeEntry::query()
            ->where('project_id', $project->id)
            ->where('public_id', $entryPublicId)
            ->firstOrFail();

        $entry->delete();
    }

    public function projectCostSummary(int $projectId): array
    {
        $entries = TimeEntry::query()->where('project_id', $projectId)->get();

        $actualCost = round($entries->sum(fn ($e) => (float) $e->hours * (float) $e->hourly_cost), 2);
        $totalHours = round($entries->sum('hours'), 2);
        $billableHours = round($entries->where('is_billable', true)->sum('hours'), 2);

        return [
            'actual_cost' => $actualCost,
            'total_hours' => $totalHours,
            'billable_hours' => $billableHours,
        ];
    }

    protected function resolveHourlyCost(?int $employeeId): float
    {
        if (! $employeeId) {
            return 0;
        }

        $employee = Employee::query()->find($employeeId);
        if (! $employee || (float) $employee->base_salary <= 0) {
            return 0;
        }

        return round((float) $employee->base_salary / 173, 2);
    }

    protected function findProject(User $user, string $publicId): Project
    {
        return Project::query()
            ->where('public_id', $publicId)
            ->where('company_id', $user->default_company_id)
            ->firstOrFail();
    }

    protected function assertPermission(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw new ApiException('Forbidden.', 403, 'FORBIDDEN');
        }
    }
}