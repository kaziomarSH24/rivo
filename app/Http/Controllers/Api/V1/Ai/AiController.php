<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Pet;
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

    /**
     * Pro Feature: Chat with RivoCare AI
     */
    public function chat(Request $request)
    {
        $request->validate([
            'topic' => 'required|string|in:symptom,nutrition,training,general',
            'prompt' => 'required|string|max:1000',
            'history' => 'nullable|array',
            'history.*.role' => 'required_with:history|string|in:user,model',
            'history.*.parts' => 'required_with:history|string',
            'pet_id' => 'nullable|exists:pets,id'
        ]);

        $topic = $request->input('topic');
        $prompt = $request->input('prompt');
        $history = $request->input('history', []);
        
        $petContext = null;
        if ($request->has('pet_id')) {
            $petContext = Pet::where('user_id', $request->user()->id)
                ->find($request->input('pet_id'));
        }

        $aiResponse = $this->aiService->generateChatResponse($topic, $prompt, $history, $petContext);
        
        return response()->json([
            'ok' => true,
            'message' => 'Chat response generated successfully',
            'data' => [
                'response' => $aiResponse
            ]
        ]);
    }
}





