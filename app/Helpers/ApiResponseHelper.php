<?php

if (!function_exists('response_success')) {
    /**
     * Sends a standard success JSON response.
     *
     * @param string $message - Success message.
     * @param mixed $data - Data to send to the client.
     * @param int $statusCode - HTTP status code (default 200).
     * @return \Illuminate\Http\JsonResponse
     */
    function response_success(string $message, $data = [], int $statusCode = 200)
    {
        return response()->json([
            'ok' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }
}

if (!function_exists('response_error')) {
    /**
     * Sends a standard error JSON response.
     *
     * @param string $message - Error message.
     * @param array $errors - Details of validation or other errors.
     * @param int $statusCode - HTTP status code (default 400).
     * @return \Illuminate\Http\JsonResponse
     */
    function response_error(string $message, array $errors = [], int $statusCode = 400)
    {
        $response = [
            'ok' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
}
