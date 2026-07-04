<?php

namespace App\Modules\Business\Services;

use App\Modules\Business\Concerns\ValidatesTenantRelations;
use App\Modules\Business\Enums\TicketStatus;
use App\Modules\Business\Models\Ticket;
use App\Modules\Core\Models\User;
use App\Support\Business\ChecksPermissions;
use App\Support\Business\GeneratesDocumentNumber;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Str;

class TicketService
{
    use ChecksPermissions, GeneratesDocumentNumber, ValidatesTenantRelations;

    public function list(User $user, array $filters = [])
    {
        $this->assertPermission($user, 'ops.ticket.read');

        $query = Ticket::query()->with('assignee')->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 25);
    }

    public function show(User $user, string $publicId): Ticket
    {
        $this->assertPermission($user, 'ops.ticket.read');

        return Ticket::query()
            ->where('public_id', $publicId)
            ->with(['assignee', 'account', 'workOrders'])
            ->firstOrFail();
    }

    public function create(User $user, array $data): Ticket
    {
        $this->assertPermission($user, 'ops.ticket.create');
        $this->assertAccountInScope($user, $data['account_id'] ?? null);

        return Ticket::create([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->default_company_id,
            'public_id' => (string) Str::uuid(),
            'ticket_number' => $this->generateNumber(
                new Ticket,
                $user->tenant_id,
                $user->default_company_id,
                'TKT-',
                'ticket_number',
            ),
            'account_id' => $data['account_id'] ?? null,
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'MEDIUM',
            'status' => TicketStatus::Open,
            'assigned_to' => null,
            'created_by' => $user->id,
        ]);
    }

    public function update(User $user, string $publicId, array $data): Ticket
    {
        $this->assertPermission($user, 'ops.ticket.update');

        $ticket = Ticket::query()->where('public_id', $publicId)->firstOrFail();

        if (in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
            throw new ApiException('Resolved or closed tickets cannot be updated.', 422, 'TICKET_CLOSED');
        }

        if (isset($data['account_id'])) {
            $this->assertAccountInScope($user, $data['account_id']);
        }

        $ticket->update(array_filter($data, fn ($v) => $v !== null));

        return $ticket->fresh(['assignee', 'account']);
    }

    public function delete(User $user, string $publicId): void
    {
        $this->assertPermission($user, 'ops.ticket.delete');

        $ticket = Ticket::query()->where('public_id', $publicId)->firstOrFail();
        $ticket->delete();
    }

    public function assign(User $user, string $publicId, int $assigneeId): Ticket
    {
        $this->assertPermission($user, 'ops.ticket.assign');
        $this->assertUserInTenant($user, $assigneeId);

        $ticket = Ticket::query()->where('public_id', $publicId)->firstOrFail();

        if (in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
            throw new ApiException('Cannot assign a resolved or closed ticket.', 422, 'TICKET_CLOSED');
        }

        $ticket->update([
            'assigned_to' => $assigneeId,
            'status' => TicketStatus::InProgress,
        ]);

        return $ticket->fresh(['assignee', 'account']);
    }

    public function resolve(User $user, string $publicId): Ticket
    {
        $this->assertPermission($user, 'ops.ticket.resolve');

        $ticket = Ticket::query()->where('public_id', $publicId)->firstOrFail();

        if (in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
            throw new ApiException('Ticket is already resolved or closed.', 422, 'TICKET_ALREADY_RESOLVED');
        }

        $ticket->update([
            'status' => TicketStatus::Resolved,
            'resolved_at' => now(),
        ]);

        return $ticket->fresh(['assignee', 'account']);
    }

    public function close(User $user, string $publicId): Ticket
    {
        $this->assertPermission($user, 'ops.ticket.close');

        $ticket = Ticket::query()->where('public_id', $publicId)->firstOrFail();

        if ($ticket->status === TicketStatus::Closed) {
            throw new ApiException('Ticket is already closed.', 422, 'TICKET_ALREADY_CLOSED');
        }

        $ticket->update(['status' => TicketStatus::Closed]);

        return $ticket->fresh(['assignee', 'account']);
    }
}