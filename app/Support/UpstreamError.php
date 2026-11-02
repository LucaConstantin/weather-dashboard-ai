<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * An outside service (weather or image) failed. The message is written for the person
 * using the dashboard, so the API can pass it through as it is.
 */
class UpstreamError extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 502, public readonly array $context = [])
    {
        parent::__construct($message);
    }

    public function report(): void
    {
        Log::warning($this->getMessage(), $this->context);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}
