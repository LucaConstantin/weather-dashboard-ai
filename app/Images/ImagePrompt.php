<?php

namespace App\Images;

use Illuminate\Support\Carbon;

/**
 * Builds the image prompt in code, from the weather API's numbers. No language model is
 * involved in writing it, so what the prompt says is exactly what the dashboard shows.
 */
class ImagePrompt
{
    /**
     * @param  list<array{name: string, region?: ?string, country?: ?string, weather: array}>  $places
     */
    public static function build(array $places, ?Carbon $now = null): string
    {
        $now ??= Carbon::now('UTC');
        $count = count($places);

        $lines = [
            "One wide illustration split into {$count} equal vertical panels, side by side from left to right. Each panel shows one of the places below as it looks right now.",
            'Paint every panel in the same soft, painterly style with the horizon at the same height, so the panels read as one picture.',
            'Show the weather through the sky, the light, the ground and what people wear. Use a recognisable landscape or street scene for each place.',
            'Do not write any text, letters, numbers or labels in the image.',
            '',
        ];

        foreach (array_values($places) as $i => $place) {
            $lines[] = 'Panel '.($i + 1).': '.self::describe($place, $now);
        }

        return implode("\n", $lines);
    }

    private static function describe(array $place, Carbon $now): string
    {
        $w = $place['weather'];
        $local = $now->copy()->addSeconds($w['utc_offset_seconds']);
        $light = $w['is_day'] ? 'daylight' : 'after dark';

        return sprintf(
            '%s. Local time %s, %s, %s. %s, %d°C (%s), today between %d°C and %d°C. Wind %d km/h, %s.',
            self::label($place),
            $local->format('H:i'),
            self::partOfDay((int) $local->format('G')),
            $light,
            $w['condition'],
            round($w['temperature']),
            self::feel($w['temperature']),
            round($w['low']),
            round($w['high']),
            round($w['wind_speed']),
            self::wind($w['wind_speed']),
        );
    }

    private static function label(array $place): string
    {
        $parts = array_filter([$place['name'], $place['region'] ?? null, $place['country'] ?? null]);
        // the label ends up inside a prompt: keep it to one short line
        $label = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', implode(', ', array_unique($parts)));

        return mb_substr(trim($label), 0, 120);
    }

    private static function partOfDay(int $hour): string
    {
        return match (true) {
            $hour >= 5 && $hour < 8 => 'early morning',
            $hour >= 8 && $hour < 12 => 'morning',
            $hour >= 12 && $hour < 14 => 'midday',
            $hour >= 14 && $hour < 18 => 'afternoon',
            $hour >= 18 && $hour < 22 => 'evening',
            default => 'night',
        };
    }

    private static function feel(float $celsius): string
    {
        return match (true) {
            $celsius < -10 => 'bitterly cold',
            $celsius < 0 => 'freezing',
            $celsius < 8 => 'cold',
            $celsius < 15 => 'cool',
            $celsius < 22 => 'mild',
            $celsius < 28 => 'warm',
            default => 'hot',
        };
    }

    // rough Beaufort bands, in km/h
    private static function wind(float $kmh): string
    {
        return match (true) {
            $kmh < 2 => 'calm air',
            $kmh < 12 => 'a light breeze',
            $kmh < 29 => 'a moderate breeze',
            $kmh < 50 => 'a strong wind',
            $kmh < 75 => 'a gale',
            default => 'a storm-force wind',
        };
    }
}
