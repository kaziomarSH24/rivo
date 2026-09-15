<?php

namespace App\Http\Controllers\Api\V1\Appointment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentNoteRequest;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentStatusRequest;
use App\Http\Resources\Appointment\AppointmentNoteResource;
use App\Http\Resources\Appointment\AppointmentResource;
use App\Models\Appointment;
use App\Services\Appointment\AppointmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppointmentController extends Controller
{
    protected $appointmentService;

    public function __construct(AppointmentService $appointmentService)
    {
        $this->appointmentService = $appointmentService;
    }

    /**
     * Display a listing of appointments
     */
    public function index(Request $request)
    {
        // Custom query callback to only fetch the authenticated user's appointments
        $appointments = $this->appointmentService->getAll(function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
            
            // Add date filter if requested
            if ($request->has('date')) {
                $query->whereDate('datetime', $request->date);
            }
        });

        // Calculate stats
        $stats = [
            'upcoming' => Appointment::where('user_id', $request->user()->id)->where('status', 'upcoming')->count(),
            'completed' => Appointment::where('user_id', $request->user()->id)->where('status', 'completed')->count(),
            'this_month' => Appointment::where('user_id', $request->user()->id)->whereMonth('datetime', now()->month)->whereYear('datetime', now()->year)->count(),
        ];

        return response_success('Appointments retrieved successfully', [
            'stats' => $stats,
            'appointments' => AppointmentResource::collection($appointments)->response()->getData(true)
        ]);
    }

    /**
     * Store a newly created appointment
     */
    public function store(StoreAppointmentRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $appointment = $this->appointmentService->create($data);

        return response_success('Appointment created successfully', new AppointmentResource($appointment), 201);
    }

    /**
     * Update appointment status
     */
    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment)
    {
        Gate::authorize('update', $appointment);

        $updatedAppointment = $this->appointmentService->update($appointment->id, ['status' => $request->status]);

        return response_success('Appointment status updated', new AppointmentResource($updatedAppointment));
    }

    /**
     * Store a new note for the appointment
     */
    public function storeNote(StoreAppointmentNoteRequest $request, Appointment $appointment)
    {
        Gate::authorize('update', $appointment);

        $note = $appointment->notes()->create([
            'note' => $request->note
        ]);

        return response_success('Note added successfully', new AppointmentNoteResource($note), 201);
    }

    /**
     * Get all notes for an appointment
     */
    public function getNotes(Appointment $appointment)
    {
        Gate::authorize('view', $appointment);

        $notes = $appointment->notes()->latest()->get();

        return response_success('Notes retrieved', AppointmentNoteResource::collection($notes));
    }
}



