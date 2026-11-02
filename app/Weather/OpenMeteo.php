<?php

namespace App\Weather;

use App\Support\UpstreamError;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Open-Meteo: geocoding and current weather. No API key needed.
 * Every number shown on the dashboard or put in the image prompt comes from here.
 */
class OpenMeteo
{
    /**
     * Places matching a name, best match first. There is more than one Springfield,
     * so region and country come back too.
     *
     * @return list<array{id: int, name: string, region: ?string, country: ?string, country_code: ?string, latitude: float, longitude: float, population: ?int}>
     */
    public function search(string $name): array
    {
        $response = $this->get(config('services.open_meteo.geocoding_url'), [
            'name' => $name,
            'count' => 10,
            'language' => 'en',
            'format' => 'json',
        ], 'The place search');

        return collect($response->json('results') ?? [])
            ->map(fn (array $place) => [
                'id' => (int) $place['id'],
                'name' => $place['name'],
                'region' => $place['admin1'] ?? null,
                'country' => $place['country'] ?? null,
                'country_code' => $place['country_code'] ?? null,
                'latitude' => (float) $place['latitude'],
                'longitude' => (float) $place['longitude'],
                'population' => $place['population'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * Current weather and today's high and low. Cached for 10 minutes: the dashboard and
     * the image prompt ask for the same places within seconds of each other.
     */
    public function current(float $latitude, float $longitude): array
    {
        $key = sprintf('weather:%.3f:%.3f', $latitude, $longitude);

        return Cache::remember($key, now()->addMinutes(10), function () use ($latitude, $longitude) {
            $json = $this->get(config('services.open_meteo.forecast_url'), [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'current' => 'temperature_2m,weather_code,wind_speed_10m,is_day',
                'daily' => 'temperature_2m_max,temperature_2m_min',
                'temperature_unit' => 'celsius',
                'wind_speed_unit' => 'kmh',
                'timezone' => 'auto',
                'forecast_days' => 1,
            ], 'The weather service')->json();

            $current = $json['current'] ?? null;
            $daily = $json['daily'] ?? null;

            if (! isset($current['temperature_2m'], $current['weather_code'], $current['wind_speed_10m'], $daily['temperature_2m_max'][0], $daily['temperature_2m_min'][0])) {
                throw new UpstreamError('The weather service sent an answer we could not read. Try again in a moment.', 502, ['json' => $json]);
            }

            $code = (int) $current['weather_code'];

            return [
                'temperature' => (float) $current['temperature_2m'],
                'high' => (float) $daily['temperature_2m_max'][0],
                'low' => (float) $daily['temperature_2m_min'][0],
                'wind_speed' => (float) $current['wind_speed_10m'],
                'condition' => WeatherCode::label($code),
                'kind' => WeatherCode::kind($code),
                'is_day' => (bool) ($current['is_day'] ?? true),
                'timezone' => $json['timezone'] ?? 'UTC',
                'timezone_abbreviation' => $json['timezone_abbreviation'] ?? null,
                'utc_offset_seconds' => (int) ($json['utc_offset_seconds'] ?? 0),
                'observed_at' => $current['time'] ?? null,
                'units' => ['temperature' => '°C', 'wind_speed' => 'km/h'],
            ];
        });
    }

    private function get(string $url, array $query, string $what): Response
    {
        try {
            $response = Http::acceptJson()
                ->timeout(config('services.open_meteo.timeout'))
                ->connectTimeout(5)
                ->get($url, $query);
        } catch (ConnectionException $e) {
            throw new UpstreamError("{$what} is not answering right now. Try again in a moment.", 504, ['url' => $url, 'error' => $e->getMessage()]);
        }

        if ($response->failed()) {
            throw new UpstreamError("{$what} returned an error ({$response->status()}). Try again in a moment.", 502, ['url' => $url, 'body' => $response->body()]);
        }

        return $response;
    }
}
