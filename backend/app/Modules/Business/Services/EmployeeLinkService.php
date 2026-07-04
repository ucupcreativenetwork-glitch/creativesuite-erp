<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Enums\EmployeeStatus;
use App\Modules\Business\Models\Employee;
use App\Modules\Core\Models\User;
use App\Support\Business\GeneratesDocumentNumber;
use Illuminate\Support\Str;

class EmployeeLinkService
{
    use GeneratesDocumentNumber;

    public function ensureForUser(User $user): ?Employee
    {
        if (! $user->is_active || ! $user->default_company_id) {
            return null;
        }

        if (in_array($user->account_status, ['PENDING_ACTIVATION', 'DISABLED', 'REJECTED'], true)) {
            return null;
        }

        $user->loadMissing('department');

        $linked = Employee::query()->where('user_id', $user->id)->first();
        if ($linked) {
            return $this->syncFromUser($linked, $user);
        }

        $unlinked = Employee::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereNull('user_id')
            ->where(function ($q) use ($user): void {
                $q->where('email', $user->email)
                    ->orWhere('full_name', $user->full_name);
            })
            ->first();

        if ($unlinked) {
            $unlinked->update([
                'user_id' => $user->id,
                'email' => $user->email,
                'phone' => $user->phone,
                'full_name' => $user->full_name,
                'job_title' => $user->position ?? $unlinked->job_title,
                'department' => $user->department?->name ?? $unlinked->department,
            ]);

            return $unlinked->fresh();
        }

        return Employee::create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'public_id' => (string) Str::uuid(),
            'employee_number' => $this->generateNumber(
                new Employee,
                $user->tenant_id,
                $user->default_company_id,
                'EMP-',
                'employee_number',
            ),
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'job_title' => $user->position,
            'department' => $user->department?->name,
            'base_salary' => 0,
            'status' => EmployeeStatus::Active,
            'hire_date' => now()->toDateString(),
            'user_id' => $user->id,
        ]);
    }

    public function syncAllActiveUsers(): int
    {
        $count = 0;

        User::query()
            ->where('is_active', true)
            ->whereNotNull('default_company_id')
            ->whereNotIn('account_status', ['PENDING_ACTIVATION', 'DISABLED', 'REJECTED'])
            ->orderBy('id')
            ->each(function (User $user) use (&$count): void {
                if ($this->ensureForUser($user)) {
                    $count++;
                }
            });

        return $count;
    }

    protected function syncFromUser(Employee $employee, User $user): Employee
    {
        $employee->update([
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'job_title' => $user->position ?? $employee->job_title,
            'department' => $user->department?->name ?? $employee->department,
            'company_id' => $user->default_company_id ?? $employee->company_id,
        ]);

        return $employee->fresh();
    }
}