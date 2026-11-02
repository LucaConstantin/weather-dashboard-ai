<?php

namespace App\Weather;

/**
 * WMO weather interpretation codes, as returned by Open-Meteo in `weather_code`.
 * https://open-meteo.com/en/docs (section "WMO Weather interpretation codes")
 */
class WeatherCode
{
    private const CODES = [
        0 => ['Clear sky', 'clear'],
        1 => ['Mainly clear', 'clear'],
        2 => ['Partly cloudy', 'partly-cloudy'],
        3 => ['Overcast', 'cloudy'],
        45 => ['Fog', 'fog'],
        48 => ['Freezing fog', 'fog'],
        51 => ['Light drizzle', 'drizzle'],
        53 => ['Drizzle', 'drizzle'],
        55 => ['Dense drizzle', 'drizzle'],
        56 => ['Light freezing drizzle', 'drizzle'],
        57 => ['Freezing drizzle', 'drizzle'],
        61 => ['Light rain', 'rain'],
        63 => ['Rain', 'rain'],
        65 => ['Heavy rain', 'rain'],
        66 => ['Light freezing rain', 'rain'],
        67 => ['Freezing rain', 'rain'],
        71 => ['Light snow', 'snow'],
        73 => ['Snow', 'snow'],
        75 => ['Heavy snow', 'snow'],
        77 => ['Snow grains', 'snow'],
        80 => ['Light rain showers', 'rain'],
        81 => ['Rain showers', 'rain'],
        82 => ['Violent rain showers', 'rain'],
        85 => ['Light snow showers', 'snow'],
        86 => ['Heavy snow showers', 'snow'],
        95 => ['Thunderstorm', 'storm'],
        96 => ['Thunderstorm with light hail', 'storm'],
        99 => ['Thunderstorm with heavy hail', 'storm'],
    ];

    public static function label(int $code): string
    {
        return self::CODES[$code][0] ?? 'Unknown conditions';
    }

    /** A small set of kinds the frontend turns into an icon. */
    public static function kind(int $code): string
    {
        return self::CODES[$code][1] ?? 'unknown';
    }
}
