<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Triggered when AI provider (OpenAI) is unreachable or returns an error
 * Returned as an HTTP 503
 */
class AiServiceUnavailableException extends RuntimeException
{
    // Build the exception
    public function __construct(string $message = 'AI service unavailable.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    // Render exception as a 503 JSON response
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Le service de génération IA est temporairement indisponible. Veuillez réessayer dans un instant.',
        ], 503);
    }
}
