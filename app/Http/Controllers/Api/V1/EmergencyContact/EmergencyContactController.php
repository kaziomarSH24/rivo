<?php

namespace App\Http\Controllers\Api\V1\EmergencyContact;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmergencyContact\StoreEmergencyContactRequest;
use App\Http\Requests\EmergencyContact\UpdateEmergencyContactRequest;
use App\Http\Resources\EmergencyContact\EmergencyContactResource;
use App\Models\EmergencyContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmergencyContactController extends Controller
{
    /**
     * Display a listing of the emergency contacts for the user.
     */
    public function index(Request $request)
    {
        $contacts = EmergencyContact::where('user_id', $request->user()->id)->get();
        return response_success('Emergency contacts retrieved successfully', EmergencyContactResource::collection($contacts));
    }

    /**
     * Store a newly created emergency contact in storage.
     */
    public function store(StoreEmergencyContactRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $contact = EmergencyContact::create($data);

        return response_success('Emergency contact added successfully', new EmergencyContactResource($contact), 201);
    }

    /**
     * Update the specified emergency contact in storage.
     */
    public function update(UpdateEmergencyContactRequest $request, EmergencyContact $emergencyContact)
    {
        Gate::authorize('update', $emergencyContact);

        $emergencyContact->update($request->validated());

        return response_success('Emergency contact updated successfully', new EmergencyContactResource($emergencyContact));
    }

    /**
     * Remove the specified emergency contact from storage.
     */
    public function destroy(EmergencyContact $emergencyContact)
    {
        Gate::authorize('delete', $emergencyContact);

        $emergencyContact->delete();

        return response_success('Emergency contact removed successfully', null);
    }
}



