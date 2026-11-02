<?php

namespace App\Http\Controllers;

use App\Images\GeminiImage;
use App\Images\ImagePrompt;
use App\Support\UpstreamError;
use App\Weather\OpenMeteo;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function __invoke(Request $request, OpenMeteo $openMeteo, GeminiImage $images)
    {
        $data = $request->validate([
            'places' => ['required', 'array', 'size:4'],
            'places.*.name' => ['required', 'string', 'max:100'],
            'places.*.region' => ['nullable', 'string', 'max:100'],
            'places.*.country' => ['nullable', 'string', 'max:100'],
            'places.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'places.*.longitude' => ['required', 'numeric', 'between:-180,180'],
        ], [
            'places.size' => 'Add four places before generating the image.',
        ]);

        // the image request can take most of a minute
        set_time_limit(150);

        // the weather is fetched again here (from the 10 minute cache), never taken from the browser
        $places = [];
        foreach ($data['places'] as $place) {
            try {
                $weather = $openMeteo->current((float) $place['latitude'], (float) $place['longitude']);
            } catch (UpstreamError $e) {
                throw new UpstreamError("There is no weather for {$place['name']} right now, so it can't be painted. Try again in a moment.", $e->status, $e->context);
            }
            $places[] = [...$place, 'weather' => $weather];
        }

        $prompt = ImagePrompt::build($places);

        try {
            $image = $images->generate($prompt);
        } catch (UpstreamError $e) {
            report($e);

            // keep the prompt on screen even when the image fails
            return response()->json(['message' => $e->getMessage(), 'prompt' => $prompt], $e->status);
        }

        return [
            'prompt' => $prompt,
            'image' => "data:{$image['mime']};base64,{$image['data']}",
            'model' => $image['model'],
        ];
    }
}
