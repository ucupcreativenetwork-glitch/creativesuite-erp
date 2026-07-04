<?php

namespace App\Modules\Business\Controllers\Api\V1;

use App\Modules\Business\Requests\AssignTicketRequest;
use App\Modules\Business\Requests\CreateTicketRequest;
use App\Modules\Business\Requests\UpdateTicketRequest;
use App\Modules\Business\Resources\TicketResource;
use App\Modules\Business\Services\TicketService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class TicketController extends Controller
{
    public function __construct(protected TicketService $service) {}

    public function index(): JsonResponse
    {
        $tickets = $this->service->list(auth('api')->user(), request()->only([
            'status', 'priority', 'search', 'per_page',
        ]));

        return ApiResponse::success(TicketResource::collection($tickets));
    }

    public function show(string $publicId): JsonResponse
    {
        $ticket = $this->service->show(auth('api')->user(), $publicId);

        return ApiResponse::success(new TicketResource($ticket));
    }

    public function store(CreateTicketRequest $request): JsonResponse
    {
        $ticket = $this->service->create(auth('api')->user(), $request->validated());

        return ApiResponse::success(new TicketResource($ticket), 'Ticket created.', 201);
    }

    public function update(UpdateTicketRequest $request, string $publicId): JsonResponse
    {
        $ticket = $this->service->update(auth('api')->user(), $publicId, $request->validated());

        return ApiResponse::success(new TicketResource($ticket), 'Ticket updated.');
    }

    public function destroy(string $publicId): JsonResponse
    {
        $this->service->delete(auth('api')->user(), $publicId);

        return ApiResponse::success(null, 'Ticket deleted.');
    }

    public function assign(AssignTicketRequest $request, string $publicId): JsonResponse
    {
        $ticket = $this->service->assign(
            auth('api')->user(),
            $publicId,
            $request->validated('assigned_to'),
        );

        return ApiResponse::success(new TicketResource($ticket), 'Ticket assigned.');
    }

    public function resolve(string $publicId): JsonResponse
    {
        $ticket = $this->service->resolve(auth('api')->user(), $publicId);

        return ApiResponse::success(new TicketResource($ticket), 'Ticket resolved.');
    }

    public function close(string $publicId): JsonResponse
    {
        $ticket = $this->service->close(auth('api')->user(), $publicId);

        return ApiResponse::success(new TicketResource($ticket), 'Ticket closed.');
    }
}