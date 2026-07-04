<?php

namespace App\Modules\Business\Controllers\Api\V1;

use App\Modules\Business\Requests\CreateMilestoneRequest;
use App\Modules\Business\Requests\CreateProjectRequest;
use App\Modules\Business\Requests\CreateTimeEntryRequest;
use App\Modules\Business\Requests\UpdateMilestoneRequest;
use App\Modules\Business\Requests\UpdateProjectRequest;
use App\Modules\Business\Requests\UpdateTimeEntryRequest;
use App\Modules\Business\Resources\MilestoneResource;
use App\Modules\Business\Resources\ProjectBudgetSummaryResource;
use App\Modules\Business\Resources\ProjectInvoiceResource;
use App\Modules\Business\Resources\ProjectResource;
use App\Modules\Business\Resources\TimeEntryResource;
use App\Modules\Business\Services\MilestoneService;
use App\Modules\Business\Services\ProjectService;
use App\Modules\Business\Services\TimesheetService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProjectController extends Controller
{
    public function __construct(
        protected ProjectService $service,
        protected TimesheetService $timesheetService,
        protected MilestoneService $milestoneService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $projects = $this->service->list(auth('api')->user(), $request->only(['status', 'search', 'per_page']));

        return ApiResponse::success(ProjectResource::collection($projects));
    }

    public function store(CreateProjectRequest $request): JsonResponse
    {
        $project = $this->service->create(auth('api')->user(), $request->validated());

        return ApiResponse::success(new ProjectResource($project->load('account')), 'Proyek berhasil dibuat.', 201);
    }

    public function show(string $publicId): JsonResponse
    {
        $project = $this->service->show(auth('api')->user(), $publicId);

        return ApiResponse::success(new ProjectResource($project));
    }

    public function update(UpdateProjectRequest $request, string $publicId): JsonResponse
    {
        $project = $this->service->update(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new ProjectResource($project), 'Proyek diperbarui.');
    }

    public function destroy(string $publicId): JsonResponse
    {
        $this->service->delete(auth('api')->user(), $publicId);

        return ApiResponse::success(null, 'Proyek dihapus.');
    }

    public function budgetSummary(string $publicId): JsonResponse
    {
        $summary = $this->service->budgetSummary(auth('api')->user(), $publicId);

        return ApiResponse::success(new ProjectBudgetSummaryResource($summary));
    }

    public function invoices(string $publicId): JsonResponse
    {
        $invoices = $this->service->listInvoices(auth('api')->user(), $publicId);

        return ApiResponse::success(ProjectInvoiceResource::collection($invoices));
    }

    public function timeEntries(Request $request, string $publicId): JsonResponse
    {
        $entries = $this->timesheetService->list(
            auth('api')->user(),
            $publicId,
            $request->only(['from_date', 'to_date', 'per_page']),
        );

        return ApiResponse::success(TimeEntryResource::collection($entries));
    }

    public function storeTimeEntry(CreateTimeEntryRequest $request, string $publicId): JsonResponse
    {
        $entry = $this->timesheetService->create(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new TimeEntryResource($entry), 'Timesheet entry created.', 201);
    }

    public function updateTimeEntry(UpdateTimeEntryRequest $request, string $publicId, string $entryPublicId): JsonResponse
    {
        $entry = $this->timesheetService->update(auth('api')->user(), $publicId, $entryPublicId, $request->validated());

        return ApiResponse::success(new TimeEntryResource($entry), 'Timesheet entry updated.');
    }

    public function destroyTimeEntry(string $publicId, string $entryPublicId): JsonResponse
    {
        $this->timesheetService->delete(auth('api')->user(), $publicId, $entryPublicId);

        return ApiResponse::success(null, 'Timesheet entry deleted.');
    }

    public function milestones(string $publicId): JsonResponse
    {
        $milestones = $this->milestoneService->list(auth('api')->user(), $publicId);

        return ApiResponse::success(MilestoneResource::collection($milestones));
    }

    public function storeMilestone(CreateMilestoneRequest $request, string $publicId): JsonResponse
    {
        $milestone = $this->milestoneService->create(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new MilestoneResource($milestone), 'Milestone created.', 201);
    }

    public function updateMilestone(UpdateMilestoneRequest $request, string $publicId, string $milestonePublicId): JsonResponse
    {
        $milestone = $this->milestoneService->update(auth('api')->user(), $publicId, $milestonePublicId, $request->validated());

        return ApiResponse::success(new MilestoneResource($milestone), 'Milestone updated.');
    }

    public function destroyMilestone(string $publicId, string $milestonePublicId): JsonResponse
    {
        $this->milestoneService->delete(auth('api')->user(), $publicId, $milestonePublicId);

        return ApiResponse::success(null, 'Milestone deleted.');
    }

    public function invoiceMilestone(string $publicId, string $milestonePublicId): JsonResponse
    {
        $milestone = $this->milestoneService->generateInvoice(auth('api')->user(), $publicId, $milestonePublicId);

        return ApiResponse::success(new MilestoneResource($milestone->load('invoice')), 'Invoice milestone dibuat.');
    }
}