<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherApiTest extends TestCase
{
    public function test_search_returns_every_springfield_with_region_and_country(): void
    {
        Http::fake(['geocoding-api.open-meteo.com/*' => Http::response(['results' => [
            ['id' => 4250542, 'name' => 'Springfield', 'admin1' => 'Illinois', 'country' => 'United States', 'country_code' => 'US', 'latitude' => 39.80, 'longitude' => -89.64, 'population' => 116250],
            ['id' => 4409896, 'name' => 'Springfield', 'admin1' => 'Missouri', 'country' => 'United States', 'country_code' => 'US', 'latitude' => 37.21, 'longitude' => -93.29],
        ]])]);

        $this->getJson('/api/places?q=Springfield')
            ->assertOk()
            ->assertJsonCount(2, 'places')
            ->assertJsonPath('places.0.region', 'Illinois')
            ->assertJsonPath('places.1.region', 'Missouri')
            ->assertJsonPath('places.1.population', null)
            ->assertJsonPath('message', null);
    }

    public function test_a_place_that_does_not_exist_gets_a_clear_message(): void
    {
        // Open-Meteo leaves out "results" entirely when nothing matches
        Http::fake(['geocoding-api.open-meteo.com/*' => Http::response(['generationtime_ms' => 0.4])]);

        $this->getJson('/api/places?q=Atlantisxyz')
            ->assertOk()
            ->assertJsonCount(0, 'places')
            ->assertJsonPath('message', 'No place called "Atlantisxyz" was found. Check the spelling or try a larger town nearby.');
    }

    public function test_search_needs_at_least_two_characters(): void
    {
        $this->getJson('/api/places?q=a')->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    public function test_weather_comes_back_with_units_and_local_time_offset(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response($this->forecast())]);

        $this->getJson('/api/weather?latitude=44.43&longitude=26.10')
            ->assertOk()
            ->assertJson([
                'temperature' => 7.4,
                'high' => 11.2,
                'low' => 3.9,
                'wind_speed' => 12.6,
                'condition' => 'Overcast',
                'kind' => 'cloudy',
                'is_day' => false,
                'utc_offset_seconds' => 7200,
                'units' => ['temperature' => '°C', 'wind_speed' => 'km/h'],
            ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'wind_speed_unit=kmh') && str_contains($request->url(), 'timezone=auto'));
    }

    public function test_weather_service_down_gives_a_message_not_a_crash(): void
    {
        Http::fake(['api.open-meteo.com/*' => fn () => throw new ConnectionException('timed out')]);

        $this->getJson('/api/weather?latitude=44.43&longitude=26.10')
            ->assertStatus(504)
            ->assertJsonPath('message', 'The weather service is not answering right now. Try again in a moment.');
    }

    public function test_weather_service_error_gives_a_message(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response(['error' => true, 'reason' => 'boom'], 500)]);

        $this->getJson('/api/weather?latitude=44.43&longitude=26.10')
            ->assertStatus(502)
            ->assertJsonPath('message', 'The weather service returned an error (500). Try again in a moment.');
    }

    public function test_coordinates_are_validated(): void
    {
        $this->getJson('/api/weather?latitude=120&longitude=26')->assertUnprocessable()->assertJsonValidationErrors('latitude');
    }

    private function forecast(): array
    {
        return [
            'timezone' => 'Europe/Bucharest',
            'timezone_abbreviation' => 'GMT+2',
            'utc_offset_seconds' => 7200,
            'current' => ['time' => '2026-11-02T21:15', 'temperature_2m' => 7.4, 'weather_code' => 3, 'wind_speed_10m' => 12.6, 'is_day' => 0],
            'daily' => ['time' => ['2026-11-02'], 'temperature_2m_max' => [11.2], 'temperature_2m_min' => [3.9]],
        ];
    }
}
