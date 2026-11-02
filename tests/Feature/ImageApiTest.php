<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImageApiTest extends TestCase
{
    private const GEMINI = 'generativelanguage.googleapis.com/v1beta/models/';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gemini.key' => 'test-key', 'services.gemini.image_model' => 'gemini-3.1-flash-image']);
    }

    public function test_four_places_become_one_image_and_the_prompt_comes_back_with_it(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->forecast()),
            self::GEMINI.'*' => Http::response($this->imageAnswer()),
        ]);

        $this->postJson('/api/image', ['places' => $this->places()])
            ->assertOk()
            ->assertJsonPath('image', 'data:image/png;base64,aW1hZ2U=')
            ->assertJsonPath('model', 'gemini-3.1-flash-image')
            ->assertJson(fn ($json) => $json->where('prompt', fn ($prompt) => str_contains($prompt, 'Panel 4: Reykjavík, Iceland.') && str_contains($prompt, 'Overcast, 7°C'))->etc());

        // the key goes in a header, to Google only
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'gemini-3.1-flash-image:generateContent')
            && $request->header('x-goog-api-key') === ['test-key']
            && $request['generationConfig']['responseModalities'] === ['TEXT', 'IMAGE']);
    }

    public function test_it_falls_back_to_the_next_model_when_the_first_one_is_not_found(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->forecast()),
            self::GEMINI.'gemini-3.1-flash-image:*' => Http::response(['error' => ['code' => 404, 'status' => 'NOT_FOUND']], 404),
            self::GEMINI.'gemini-3.1-flash-image-preview:*' => Http::response($this->imageAnswer()),
        ]);

        $this->postJson('/api/image', ['places' => $this->places()])
            ->assertOk()
            ->assertJsonPath('model', 'gemini-3.1-flash-image-preview');
    }

    public function test_a_missing_key_is_a_clear_message_and_the_prompt_is_still_shown(): void
    {
        config(['services.gemini.key' => null]);
        Http::fake(['api.open-meteo.com/*' => Http::response($this->forecast())]);

        $this->postJson('/api/image', ['places' => $this->places()])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Image generation is not set up yet: add GEMINI_API_KEY to .env and restart the server.')
            ->assertJson(fn ($json) => $json->has('prompt')->etc());

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'googleapis'));
    }

    public function test_an_image_model_that_times_out_gets_a_message(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->forecast()),
            self::GEMINI.'*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out'),
        ]);

        $this->postJson('/api/image', ['places' => $this->places()])
            ->assertStatus(504)
            ->assertJsonPath('message', 'The image model did not answer within 60 seconds. Try again in a moment.');
    }

    public function test_a_refused_prompt_gets_a_message(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->forecast()),
            self::GEMINI.'*' => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']]),
        ]);

        $this->postJson('/api/image', ['places' => $this->places()])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The image model refused this prompt (SAFETY).');
    }

    public function test_an_answer_with_text_but_no_image_gets_a_message(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->forecast()),
            self::GEMINI.'*' => Http::response(['candidates' => [[
                'finishReason' => 'IMAGE_SAFETY',
                'content' => ['parts' => [['text' => 'I can not make this image.']]],
            ]]]),
        ]);

        $this->postJson('/api/image', ['places' => $this->places()])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The image model did not return an image (IMAGE_SAFETY). It said: "I can not make this image." Try again, or change one of the places.');
    }

    public function test_a_bad_key_and_a_used_up_quota_have_their_own_messages(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response($this->forecast()),
            self::GEMINI.'*' => Http::sequence()
                ->push(['error' => ['status' => 'INVALID_ARGUMENT', 'details' => [['reason' => 'API_KEY_INVALID']]]], 400)
                ->push(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429),
        ]);

        $this->postJson('/api/image', ['places' => $this->places()])
            ->assertStatus(502)
            ->assertJsonPath('message', 'The image service rejected the API key. Check GEMINI_API_KEY in .env.');

        $this->postJson('/api/image', ['places' => $this->places()])
            ->assertStatus(429)
            ->assertJsonPath('message', 'The image service is rate limited or out of quota for this key. Wait a minute and try again.');
    }

    public function test_missing_weather_for_one_place_names_that_place(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::sequence()
                ->push($this->forecast())
                ->push($this->forecast())
                ->push(['error' => true], 500),
        ]);

        $this->postJson('/api/image', ['places' => $this->places()])
            ->assertStatus(502)
            ->assertJsonPath('message', "There is no weather for Tokyo right now, so it can't be painted. Try again in a moment.");

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'googleapis'));
    }

    public function test_it_needs_exactly_four_places(): void
    {
        $this->postJson('/api/image', ['places' => array_slice($this->places(), 0, 3)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.places.0', 'Add four places before generating the image.');
    }

    private function places(): array
    {
        return [
            ['name' => 'Springfield', 'region' => 'Illinois', 'country' => 'United States', 'latitude' => 39.80, 'longitude' => -89.64],
            ['name' => 'Bucharest', 'region' => null, 'country' => 'Romania', 'latitude' => 44.43, 'longitude' => 26.10],
            ['name' => 'Tokyo', 'region' => null, 'country' => 'Japan', 'latitude' => 35.69, 'longitude' => 139.69],
            ['name' => 'Reykjavík', 'region' => null, 'country' => 'Iceland', 'latitude' => 64.14, 'longitude' => -21.90],
        ];
    }

    private function forecast(): array
    {
        return [
            'timezone' => 'Europe/Bucharest',
            'utc_offset_seconds' => 7200,
            'current' => ['time' => '2026-11-02T21:15', 'temperature_2m' => 7.4, 'weather_code' => 3, 'wind_speed_10m' => 12.6, 'is_day' => 0],
            'daily' => ['temperature_2m_max' => [11.2], 'temperature_2m_min' => [3.9]],
        ];
    }

    private function imageAnswer(): array
    {
        return ['candidates' => [[
            'finishReason' => 'STOP',
            'content' => ['parts' => [
                ['text' => 'Here is the picture.'],
                ['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode('image')]],
            ]],
        ]]];
    }
}
