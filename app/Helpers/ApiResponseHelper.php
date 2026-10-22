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

if (!function_exists('format_media_url')) {
    /**
     * Formats a stored media path or external link to a fully qualified URL.
     *
     * - Returns null if the path is null or empty.
     * - Preserves external HTTP/HTTPS links (e.g. Unsplash, CDN).
     * - Converts local storage paths (e.g. 'stores/logos/x.png') to full public asset URLs.
     * - Supports an optional default directory (e.g. 'categories', 'stores/logos') for plain filenames.
     *
     * @param string|null $path
     * @param string|null $defaultDir
     * @return string|null
     */
    function format_media_url(?string $path, ?string $defaultDir = null): ?string
    {
        if ($path === null) {
            return null;
        }

        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        $cleanPath = ltrim($trimmed, '/');

        if (str_starts_with($cleanPath, 'storage/')) {
            return asset($cleanPath);
        }

        if ($defaultDir && !str_contains($cleanPath, '/')) {
            return asset('storage/' . trim($defaultDir, '/') . '/' . $cleanPath);
        }

        return asset('storage/' . $cleanPath);
    }
}
