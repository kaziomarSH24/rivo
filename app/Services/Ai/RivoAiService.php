<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RivoAiService
{
    protected $apiKey;
    protected $apiUrl;
    // Using actual Gemini 1.5 models for better capability and system_instruction support
    protected $models = [
        'gemini-3.5-flash',
        'gemini-3.6-flash',
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
     * Generate a chat response for specific features
     */
    public function generateChatResponse($topic, $prompt, $history = [], $petContext = null)
    {
        if (empty($this->apiKey)) {
            return "RivoCare AI: API Key is missing.";
        }

        $systemInstruction = $this->getSystemInstruction($topic, $petContext);

        // Format history for Gemini API
        $contents = [];
        if (!empty($history)) {
            foreach ($history as $msg) {
                // Expecting $msg to have 'role' ('user' or 'model') and 'parts' (string text)
                $contents[] = [
                    'role' => $msg['role'] === 'model' ? 'model' : 'user',
                    'parts' => [['text' => $msg['parts']]]
                ];
            }
        }

        // Add the current prompt
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $prompt]]
        ];

        return $this->sendChatToGemini($contents, $systemInstruction);
    }

    protected function getSystemInstruction($topic, $petContext)
    {
        $base = "You are RivoCare AI, a smart pet health assistant within the Rivo app. ";
        $petInfo = "";
        
        if ($petContext) {
            $petInfo = "The user is asking about their pet: {$petContext->name} (Type: {$petContext->type}, Breed: {$petContext->breed}, Age: {$petContext->age_years}y {$petContext->age_months}m, Weight: {$petContext->weight}kg). ";
            
            try {
                $vaultService = app(\App\Services\Pet\HealthVaultService::class);
                $healthData = $vaultService->getDashboard($petContext);
                $healthScore = $healthData['health_score']['score'] ?? 0;
                $healthStatus = $healthData['health_score']['status'] ?? 'Unknown';
                
                $petInfo .= "Current Health Score: {$healthScore}/100 (Status: {$healthStatus}). ";
            } catch (\Exception $e) {
                // Ignore if fails
            }
        }

        $strictDisclaimer = "STRICT INSTRUCTION: You MUST ONLY answer questions related to your specific role described below. If the user asks about ANYTHING else (like politics, programming, general knowledge, or topics outside your role), politely decline and say you are only here to help with your assigned topic. ";

        return match ($topic) {
            'symptom' => $base . $petInfo . $strictDisclaimer . "Your role is a Pet Symptom Checker. Ask step-by-step diagnostic questions to understand the pet's symptoms. ALWAYS include a disclaimer that you are an AI, not a vet, and advise seeing a vet for emergencies. Do not answer questions unrelated to pet symptoms or health.",

            'nutrition' => $base . $petInfo . $strictDisclaimer . "Your role is an Expert Pet Nutritionist. Provide diet, feeding guidance, and nutritional advice. Do not answer questions unrelated to pet food or nutrition.",

            'training' => $base . $petInfo . $strictDisclaimer . "Your role is an Expert Pet Trainer. Provide tips on behavior, obedience, and training techniques. Do not answer questions unrelated to pet training or behavior.",

            'general' => $base . $petInfo . $strictDisclaimer . "Your role is a General Pet Assistant. Answer general questions about pet care, breeds, and Rivo app features. Keep your answers concise and friendly. Do not answer questions unrelated to pets.",

            default => $base . $petInfo . "You are a helpful pet assistant."
        };
    }

    protected function sendChatToGemini($contents, $systemInstruction)
    {
        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $systemInstruction]]
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
            ]
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '?key=' . $this->apiKey, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? "I'm sorry, I couldn't process that.";
            }

            Log::error('Gemini API Error: ' . $response->body());

            // Auto-switch model on failure logic (simplified)
            $this->currentModelIndex++;
            if ($this->currentModelIndex < count($this->models)) {
                $this->apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$this->models[$this->currentModelIndex]}:generateContent";
                return $this->sendChatToGemini($contents, $systemInstruction); // Retry
            }
            $this->currentModelIndex = 0; // Reset

            return "RivoCare AI is currently experiencing high load. Please try again later.";

        } catch (\Exception $e) {
            Log::error('Gemini Exception: ' . $e->getMessage());
            return "RivoCare AI encountered an error. Please try again.";
        }
    }

    /**
     * Send a single prompt to Gemini API (Legacy)
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









