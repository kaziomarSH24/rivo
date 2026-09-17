<?php

namespace App\Http\Controllers\Api\V1\Pet;

use App\Http\Controllers\Controller;
use App\Models\CareTask;
use App\Models\Pet;
use App\Services\Pet\CareService;
use App\Http\Requests\StoreCareTaskRequest;
use App\Http\Resources\CareTaskResource;
use Illuminate\Http\Request;

class CareController extends Controller
{
    protected $careService;

    public function __construct(CareService $careService)
    {
        $this->careService = $careService;
    }

    public function index(Request $request, Pet $pet)
    {
        $this->authorize('view', $pet);

        $dashboard = $this->careService->getCareDashboard($pet);

        return response()->json([
            'ok' => true,
            'message' => 'Care dashboard retrieved successfully',
            'data' => [
                'summary' => $dashboard['summary'],
                'tasks' => CareTaskResource::collection($dashboard['tasks'])
            ]
        ]);
    }

    public function store(StoreCareTaskRequest $request, Pet $pet)
    {
        $this->authorize('update', $pet);

        $task = $this->careService->createTask($pet, $request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Care task created successfully',
            'data' => new CareTaskResource($task)
        ], 201);
    }

    public function complete(Request $request, Pet $pet, CareTask $careTask)
    {
        $this->authorize('update', $pet);

        if ($careTask->pet_id !== $pet->id) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized task'], 403);
        }

        $log = $this->careService->completeTask($pet, $careTask);

        if (!$log) {
            return response()->json([
                'ok' => false,
                'message' => 'Task already completed for today'
            ], 400);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Task marked as completed',
            'data' => [
                'xp_earned' => $log->xp_earned
            ]
        ]);
    }
}

