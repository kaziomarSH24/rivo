<?php

namespace App\Http\Controllers\Api\V1\Pet;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Models\HealthDocument;
use App\Services\Pet\HealthVaultService;
use App\Http\Requests\StoreHealthDocumentRequest;
use App\Http\Requests\StorePetWeightLogRequest;
use App\Http\Resources\HealthDocumentResource;
use App\Http\Resources\PetWeightLogResource;
use Illuminate\Http\Request;

class HealthVaultController extends Controller
{
    protected $vaultService;

    public function __construct(HealthVaultService $vaultService)
    {
        $this->vaultService = $vaultService;
    }

    public function getDashboard(Request $request, Pet $pet)
    {
        $this->authorize('view', $pet);

        $data = $this->vaultService->getDashboard($pet);

        return response()->json([
            'ok' => true,
            'message' => 'Health Vault dashboard retrieved successfully',
            'data' => $data
        ]);
    }

    public function getDocuments(Request $request, Pet $pet)
    {
        $this->authorize('view', $pet);
        
        $documents = $pet->healthDocuments()->latest()->get();

        return response()->json([
            'ok' => true,
            'message' => 'Documents retrieved successfully',
            'data' => HealthDocumentResource::collection($documents)
        ]);
    }

    public function uploadDocument(StoreHealthDocumentRequest $request, Pet $pet)
    {
        $this->authorize('update', $pet);

        try {
            $doc = $this->vaultService->uploadDocument(
                $pet, 
                $request->file('document'), 
                $request->title
            );

            return response()->json([
                'ok' => true,
                'message' => 'Document uploaded successfully',
                'data' => new HealthDocumentResource($doc)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], $e->getCode() ?: 400);
        }
    }

    public function deleteDocument(Request $request, Pet $pet, HealthDocument $document)
    {
        $this->authorize('update', $pet);

        if ($document->pet_id !== $pet->id) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized document'], 403);
        }

        $this->vaultService->deleteDocument($document);

        return response()->json([
            'ok' => true,
            'message' => 'Document deleted successfully'
        ]);
    }

    public function getWeightLogs(Request $request, Pet $pet)
    {
        $this->authorize('view', $pet);
        
        $logs = $pet->weightLogs()->orderBy('logged_at', 'asc')->get();

        return response()->json([
            'ok' => true,
            'message' => 'Weight logs retrieved successfully',
            'data' => PetWeightLogResource::collection($logs)
        ]);
    }

    public function logWeight(StorePetWeightLogRequest $request, Pet $pet)
    {
        $this->authorize('update', $pet);

        $log = $this->vaultService->logWeight($pet, $request->weight_kg);

        return response()->json([
            'ok' => true,
            'message' => 'Weight logged successfully',
            'data' => new PetWeightLogResource($log)
        ], 201);
    }
}
