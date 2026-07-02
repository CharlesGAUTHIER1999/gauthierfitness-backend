<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Triggered when AI provider (OpenAI) is unreachable or returns an error (timeout, 429, 5xx, unexpected response).
 * Returned as an HTTP 503 so that the client receives a clear message rather than a generic 500.
 */
class AiServiceUnavailableException extends RuntimeException
{
    /** Build the exception, optionally wrapping the underlying cause. */
    public function __construct(string $message = 'AI service unavailable.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /** Render this exception as a 503 JSON response for API clients. */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Le service de génération IA est temporairement indisponible. Veuillez réessayer dans un instant.',
        ], 503);
    }
}
