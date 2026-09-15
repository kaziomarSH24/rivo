<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RivoAiService
{
    protected $apiKey;
    protected $apiUrl;
    protected $models = [
        'gemini-3.8-flash',
        'gemini-3.6-flash',
        'gemini-3.5-flash'
    ];
    protected $currentModelIndex = 0;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
        $model = $this->models[$this->currentModelIndex];
        $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
    }

    /**
     * Get a short AI tip based on upcoming appointments
     */
    public function getAppointmentInsights($appointments)
    {
        if (empty($this->apiKey)) {
            return "RivoCare AI: API Key is missing.";
        }

        if ($appointments->isEmpty()) {
            return "No upcoming appointments. Keep your pet active and healthy!";
        }

        // Prepare context
        $context = "You are RivoCare AI, a smart pet health assistant. The user has the following upcoming appointments for their pets:\n";
        foreach ($appointments as $app) {
            $context .= "- Pet: " . $app->pet->name . ", Type: " . $app->type . ", Date: " . $app->datetime->format('Y-m-d H:i') . ", Title: " . $app->title . "\n";
        }

        $context .= "\nBased on these appointments, write a ONE sentence helpful tip or reminder for the user (Max 15 words). Example: 'Pedro's vet visit is tomorrow - make sure they've eaten lightly.' Be conversational and friendly.";

        return $this->generateContent($context);
    }

    /**
     * Send a prompt to Gemini API
     */
    protected function generateContent($prompt)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '?key=' . $this->apiKey, [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt]
                                ]
                            ]
                        ]
                    ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? "Stay healthy!";
            }

            Log::error('Gemini API Error: ' . $response->body());
            return "Ensure your pet stays hydrated today!";

        } catch (\Exception $e) {
            Log::error('Gemini Exception: ' . $e->getMessage());
            return "Ensure your pet stays hydrated today!";
        }
    }
}





