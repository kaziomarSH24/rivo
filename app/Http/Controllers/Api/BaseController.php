<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BaseService;
use Illuminate\Http\Request;

/**
 * This is a base for all API controllers.
 * Its purpose is to fetch data from the service layer and return JSON responses.
 */
abstract class BaseController extends Controller
{
    protected BaseService $service;

    public function __construct(BaseService $service)
    {
        $this->service = $service;
    }

    /**
    * Displays a list of all resources.
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);

        return response()->json($this->service->getAll([], $perPage));
    }

    /**
    * Displays a specific resource.
     */
    public function show(int $id)
    {
        return response()->json($this->service->getById($id));
    }

    /**
     * Creates a new resource.
     * Note: This method should be overridden in specific controllers using FormRequest.
     */
    // public function store(Request $request)
    // {
    //     $record = $this->service->create($request->all());
    //     return response()->json($record, 201);
    // }

    // /**
    // * Updates an existing resource.
    // * Note: This method should be overridden in specific controllers using FormRequest.
    //  */
    // public function update(Request $request, int $id)
    // {
    //     $record = $this->service->update($id, $request->all());
    //     return response()->json($record);
    // }

    /**
    * Deletes a resource.
     */
    public function destroy(int $id)
    {
        $this->service->delete($id);
        return response()->json(null, 204); // 204 No Content
    }
}

