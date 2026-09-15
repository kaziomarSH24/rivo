<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Ai\RivoAiService;
use Illuminate\Http\Request;

class AiController extends Controller
{
    protected $aiService;

    public function __construct(RivoAiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Get AI Insights for Appointments
     */
    public function appointmentInsights(Request $request)
    {
        // Get upcoming appointments for the next 7 days
        $appointments = Appointment::with('pet')
            ->where('user_id', $request->user()->id)
            ->where('status', 'upcoming')
            ->whereBetween('datetime', [now(), now()->addDays(7)])
            ->get();
        $insight = $this->aiService->getAppointmentInsights($appointments);

        return response_success('AI insight generated', [
            'insight_text' => trim($insight)
        ]);
    }
}



