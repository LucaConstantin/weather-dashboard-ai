<?php

namespace App\Images;

use App\Support\UpstreamError;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Text to image through the Gemini API (generateContent with an image response).
 * The key is read from .env on the server and never reaches the browser.
 */
class GeminiImage
{
    /**
     * @return array{mime: string, data: string, model: string}
     */
    public function generate(string $prompt): array
    {
        $key = config('services.gemini.key');
        if (blank($key)) {
            throw new UpstreamError('Image generation is not set up yet: add GEMINI_API_KEY to .env and restart the server.', 503);
        }

        $models = array_values(array_unique(array_filter([
            config('services.gemini.image_model'),
            ...config('services.gemini.fallback_models'),
        ])));

        foreach ($models as $model) {
            $response = $this->call($key, $model, $prompt);

            // 404: this key can't see that model name, so try the next one before giving up
            if ($response->status() === 404) {
                continue;
            }

            return $this->imageFrom($response, $model);
        }

        throw new UpstreamError('None of the image models is available for this API key ('.implode(', ', $models).'). Set GEMINI_IMAGE_MODEL in .env to one your key can use.', 502);
    }

    private function call(string $key, string $model, string $prompt): Response
    {
        $timeout = config('services.gemini.timeout');

        try {
            return Http::withHeaders(['x-goog-api-key' => $key])
                ->acceptJson()
                ->timeout($timeout)
                ->connectTimeout(10)
                ->post(config('services.gemini.base_url')."/models/{$model}:generateContent", [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'responseModalities' => ['TEXT', 'IMAGE'],
                        'imageConfig' => ['aspectRatio' => '16:9'],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new UpstreamError("The image model did not answer within {$timeout} seconds. Try again in a moment.", 504, ['model' => $model, 'error' => $e->getMessage()]);
        }
    }

    private function imageFrom(Response $response, string $model): array
    {
        $status = $response->status();
        $context = ['model' => $model, 'status' => $status, 'body' => Str::limit($response->body(), 2000)];

        if (in_array($status, [401, 403], true) || ($status === 400 && str_contains($response->body(), 'API_KEY_INVALID'))) {
            throw new UpstreamError('The image service rejected the API key. Check GEMINI_API_KEY in .env.', 502, $context);
        }
        if ($status === 429) {
            throw new UpstreamError('The image service is rate limited or out of quota for this key. Wait a minute and try again.', 429, $context);
        }
        if (in_array($status, [500, 502, 503], true)) {
            throw new UpstreamError('The image model is busy or down right now. Try again in a minute.', 503, $context);
        }
        if ($response->failed()) {
            throw new UpstreamError("The image service returned an error ({$status}).", 502, $context);
        }

        if ($block = $response->json('promptFeedback.blockReason')) {
            throw new UpstreamError("The image model refused this prompt ({$block}).", 422, $context);
        }

        $candidate = $response->json('candidates.0') ?? [];
        foreach ($candidate['content']['parts'] ?? [] as $part) {
            if (! empty($part['inlineData']['data'])) {
                return [
                    'mime' => $part['inlineData']['mimeType'] ?? 'image/png',
                    'data' => $part['inlineData']['data'],
                    'model' => $model,
                ];
            }
        }

        // no image: a safety stop, or the model answered with text instead
        $reason = $candidate['finishReason'] ?? null;
        $said = collect($candidate['content']['parts'] ?? [])->pluck('text')->filter()->implode(' ');
        $message = 'The image model did not return an image'.($reason && $reason !== 'STOP' ? " ({$reason})" : '').'.';
        if ($said !== '') {
            $message .= ' It said: "'.Str::limit(trim($said), 200).'"';
        }

        throw new UpstreamError($message.' Try again, or change one of the places.', 422, $context);
    }
}
