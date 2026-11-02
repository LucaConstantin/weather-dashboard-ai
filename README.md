# Four places, one picture

A small weather dashboard: add four places, see the current weather in each, and turn it into one AI image whose prompt is built in code from the weather data.

Laravel 12 (PHP) for the API, Vue 3 on Vite for the page, Open-Meteo for weather, Gemini for the image.

## Run it from a fresh clone

Needs PHP 8.2+, Composer and Node 20+.

```bash
git clone <this repo> weather-dashboard-ai
cd weather-dashboard-ai
composer install
npm install
npm run build
cp .env.example .env
php artisan key:generate
```

Put your Gemini API key in `.env` (`GEMINI_API_KEY=...`), then:

```bash
php artisan serve --no-reload
```

Open http://127.0.0.1:8000. No database or migration is needed. Tests: `php artisan test`.

`--no-reload` lets `php artisan serve` start the 4 workers set in `.env` (`PHP_CLI_SERVER_WORKERS`), so the weather cards keep loading while an image is being generated. On Windows PHP's built-in server has a single worker, so requests wait for the image to finish.

## APIs and why

- **Weather: [Open-Meteo](https://open-meteo.com).** No key, and the geocoding answer has region, country and population, which is what you need to tell the Springfields apart. The forecast call returns the time zone and UTC offset, so the local time comes from the same answer, and units are set in the request (°C, km/h).
- **Image: Gemini, `gemini-3.1-flash-image` by default.** One `generateContent` call returns the image inline, it accepts an aspect ratio (16:9 for four panels side by side), and I already use the Gemini API in other projects. Google's docs use more than one name for the same image model, so if the configured name answers 404 the app tries `gemini-3.1-flash-image-preview` and then `gemini-2.5-flash-image`. Set `GEMINI_IMAGE_MODEL` in `.env` to pick another one. Image models are not on Gemini's free tier, so the key needs billing.

## How it fits together

- `app/Weather/OpenMeteo.php`: place search and current weather. Every number on the page and in the prompt comes from here. Weather is cached for 10 minutes.
- `app/Images/ImagePrompt.php`: builds the prompt from the weather data in plain code. No language model writes it.
- `app/Images/GeminiImage.php`: calls Gemini and turns every failure into a message for the user.
- `POST /api/image` fetches the weather again on the server instead of trusting numbers from the browser, and returns the prompt even when the image fails.
- The four places live in `localStorage`, so they survive a reload without accounts.

Failures: a place that doesn't exist, the weather API being down, a missing or rejected key, quota, a timeout (60 s, `GEMINI_TIMEOUT`), a refused prompt or an answer without an image all show a message where they happen, and the rest of the page keeps working. They are covered in `tests/Feature`.

## What doesn't work yet

- The image request is one long HTTP call (up to a minute). With one more day I would move it to a queued job and let the page poll, so it doesn't depend on server workers or proxy timeouts.
- Images aren't saved: a reload loses the last image, and every click costs a generation.
- The search results can't be moved through with the arrow keys yet.
- There are PHP tests for the API and the prompt, but no browser test for the page.

## How I used AI

I built this with Claude Code (Claude Opus 5.5). I gave it the task text and my choices: Laravel and Vue because they're my main stack, Open-Meteo, and Gemini for the image. It wrote the code and the tests in small steps, one commit per step, as the history shows. Before writing the tests it checked the real Open-Meteo answers, for example that a search with no match has no `results` key at all.

What changed or got thrown away on the way:

- Laravel's default database sessions, cache and queue went: files and `sync` are enough here, and a fresh clone runs without a migration.
- Setting `PHP_CLI_SERVER_WORKERS` alone did nothing: `php artisan serve` only uses it with `--no-reload`, which is why the run command has that flag.
- The image model name: Google's docs show more than one name for the same model, so instead of guessing one the app tries a short list on 404.
- Axios and the welcome page from the skeleton were removed: the page uses `fetch`, wrapped so a failing call returns a message instead of throwing.
