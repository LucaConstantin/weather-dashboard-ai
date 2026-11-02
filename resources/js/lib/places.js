// "Springfield, Illinois, United States": enough to tell the Springfields apart.
export function placeLabel(place) {
    return [place.name, place.region, place.country].filter(Boolean).join(', ');
}

export function placeDetail(place) {
    return [place.region, place.country].filter(Boolean).join(', ');
}

const ICONS = {
    clear: ['☀️', '🌙'],
    'partly-cloudy': ['⛅', '☁️'],
    cloudy: ['☁️', '☁️'],
    fog: ['🌫️', '🌫️'],
    drizzle: ['🌦️', '🌧️'],
    rain: ['🌧️', '🌧️'],
    snow: ['❄️', '❄️'],
    storm: ['⛈️', '⛈️'],
};

export function weatherIcon(kind, isDay) {
    const pair = ICONS[kind] ?? ['🌡️', '🌡️'];

    return isDay ? pair[0] : pair[1];
}
