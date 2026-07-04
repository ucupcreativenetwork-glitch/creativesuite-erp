<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Concerns\ValidatesTenantRelations;
use App\Modules\Business\Enums\WorkOrderStatus;
use App\Modules\Business\Models\Ticket;
use App\Modules\Business\Models\WorkOrder;
use App\Modules\Core\Models\User;
use App\Support\Business\ChecksPermissions;
use App\Support\Business\GeneratesDocumentNumber;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Str;

class WorkOrderService
{
    use ChecksPermissions, GeneratesDocumentNumber, ValidatesTenantRelations;

    public function list(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'ops.work_order.read');

        $query = WorkOrder::query()->with('technician')->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('work_order_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function show(User $user, string $publicId): WorkOrder
    {
        $this->assertPermission($user, 'ops.work_order.read');

        return WorkOrder::query()
            ->where('public_id', $publicId)
            ->with(['technician', 'ticket', 'account'])
            ->firstOrFail();
    }

    public function create(User $user, array $data): WorkOrder
    {
        $this->assertPermission($user, 'ops.work_order.create');
        $this->assertAccountInScope($user, $data['account_id'] ?? null);
        $this->assertTicketInScope($user, $data['ticket_id'] ?? null);

        return WorkOrder::create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'public_id' => (string) Str::uuid(),
            'work_order_number' => $this->generateNumber(
                new WorkOrder,
                $user->tenant_id,
                $user->default_company_id,
                'WO-',
                'work_order_number',
            ),
            'ticket_id' => $data['ticket_id'] ?? null,
            'account_id' => $data['account_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => WorkOrderStatus::Scheduled,
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'technician_id' => null,
            'created_by' => $user->id,
        ]);
    }

    public function update(User $user, string $publicId, array $data): WorkOrder
    {
        $this->assertPermission($user, 'ops.work_order.update');

        $workOrder = WorkOrder::query()->where('public_id', $publicId)->firstOrFail();

        if (in_array($workOrder->status, [WorkOrderStatus::Completed, WorkOrderStatus::Cancelled], true)) {
            throw new ApiException('Completed or cancelled work orders cannot be updated.', 422, 'WORK_ORDER_CLOSED');
        }

        if (isset($data['account_id'])) {
            $this->assertAccountInScope($user, $data['account_id']);
        }

        if (isset($data['ticket_id'])) {
            $this->assertTicketInScope($user, $data['ticket_id']);
        }

        $workOrder->update(array_filter($data, fn ($v) => $v !== null));

        return $workOrder->fresh(['technician', 'ticket', 'account']);
    }

    public function delete(User $user, string $publicId): void
    {
        $this->assertPermission($user, 'ops.work_order.delete');

        $workOrder = WorkOrder::query()->where('public_id', $publicId)->firstOrFail();
        $workOrder->delete();
    }

    public function assign(User $user, string $publicId, int $technicianId): WorkOrder
    {
        $this->assertPermission($user, 'ops.work_order.assign');
        $this->assertUserInTenant($user, $technicianId);

        $workOrder = WorkOrder::query()->where('public_id', $publicId)->firstOrFail();

        if (in_array($workOrder->status, [WorkOrderStatus::Completed, WorkOrderStatus::Cancelled], true)) {
            throw new ApiException('Cannot assign a completed or cancelled work order.', 422, 'WORK_ORDER_CLOSED');
        }

        $workOrder->update([
            'technician_id' => $technicianId,
            'status' => WorkOrderStatus::InProgress,
        ]);

        return $workOrder->fresh(['technician', 'ticket', 'account']);
    }

    public function complete(User $user, string $publicId): WorkOrder
    {
        $this->assertPermission($user, 'ops.work_order.complete');

        $workOrder = WorkOrder::query()->where('public_id', $publicId)->firstOrFail();

        if ($workOrder->status === WorkOrderStatus::Completed) {
            throw new ApiException('Work order is already completed.', 422, 'WORK_ORDER_ALREADY_COMPLETED');
        }

        if ($workOrder->status === WorkOrderStatus::Cancelled) {
            throw new ApiException('Cancelled work orders cannot be completed.', 422, 'WORK_ORDER_CANCELLED');
        }

        $workOrder->update([
            'status' => WorkOrderStatus::Completed,
            'completed_at' => now(),
        ]);

        return $workOrder->fresh(['technician', 'ticket', 'account']);
    }

    protected function assertTicketInScope(User $user, ?int $ticketId): void
    {
        if ($ticketId === null) {
            return;
        }

        $exists = Ticket::query()
            ->where('id', $ticketId)
            ->where('tenant_id', $user->tenant_id)
            ->where('company_id', $user->default_company_id)
            ->exists();

        if (! $exists) {
            throw new ApiException('Ticket not found in current company.', 422, 'INVALID_TICKET');
        }
    }
}