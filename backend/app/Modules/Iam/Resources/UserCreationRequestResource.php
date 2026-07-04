<?php

namespace App\Modules\Iam\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserCreationRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'request_number' => $this->request_number,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'position' => $this->position,
            'notes' => $this->notes,
            'status' => $this->status?->value ?? $this->status,
            'current_approval_level' => $this->current_approval_level,
            'rejection_reason' => $this->rejection_reason,
            'revision_notes' => $this->revision_notes,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->public_id,
                'code' => $this->department->code,
                'name' => $this->department->name,
            ]),
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch->id,
                'code' => $this->branch->code,
                'name' => $this->branch->name,
            ]),
            'requested_role' => $this->whenLoaded('requestedRole', fn () => [
                'id' => $this->requestedRole->id,
                'code' => $this->requestedRole->code,
                'name' => $this->requestedRole->name,
            ]),
            'requester' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester->public_id,
                'full_name' => $this->requester->full_name,
                'email' => $this->requester->email,
            ]),
            'created_user' => $this->whenLoaded('createdUser', fn () => $this->createdUser ? [
                'id' => $this->createdUser->public_id,
                'email' => $this->createdUser->email,
            ] : null),
            'history' => ApprovalHistoryResource::collection($this->whenLoaded('history')),
            'workflow' => $this->whenLoaded('workflow', fn () => [
                'id' => $this->workflow->public_id,
                'name' => $this->workflow->name,
                'steps' => $this->workflow->steps->map(fn ($s) => [
                    'step_order' => $s->step_order,
                    'approver_role_code' => $s->approver_role_code,
                ]),
            ]),
        ];
    }
}