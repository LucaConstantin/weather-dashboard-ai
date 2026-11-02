<?php

namespace App\Http\Controllers;

use App\Weather\OpenMeteo;
use Illuminate\Http\Request;

class PlaceController extends Controller
{
    public function __invoke(Request $request, OpenMeteo $openMeteo)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $places = $openMeteo->search(trim($data['q']));

        return [
            'places' => $places,
            'message' => $places === [] ? "No place called \"{$data['q']}\" was found. Check the spelling or try a larger town nearby." : null,
        ];
    }
}
