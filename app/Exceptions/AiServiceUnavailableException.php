<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Levée lorsque le fournisseur IA (OpenAI) est injoignable ou renvoie une erreur
 * (timeout, 429, 5xx, réponse inattendue). Rendue en HTTP 503 afin que le client
 * reçoive un message clair plutôt qu'une 500 générique.
 */
class AiServiceUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'AI service unavailable.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Le service de génération IA est temporairement indisponible. Veuillez réessayer dans un instant.',
        ], 503);
    }
}
