<?php

namespace App\Http\Controllers;

use App\Weather\OpenMeteo;
use Illuminate\Http\Request;

class WeatherController extends Controller
{
    public function __invoke(Request $request, OpenMeteo $openMeteo)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return $openMeteo->current((float) $data['latitude'], (float) $data['longitude']);
    }
}
