// The four places live in the browser, so they survive a reload without accounts or a database.
const KEY = 'weather-dashboard.places.v1';
const SLOTS = 4;

export function loadPlaces() {
    try {
        const saved = JSON.parse(localStorage.getItem(KEY));
        if (!Array.isArray(saved)) {
            return emptySlots();
        }

        return emptySlots().map((_, index) => (isPlace(saved[index]) ? saved[index] : null));
    } catch {
        return emptySlots();
    }
}

export function savePlaces(places) {
    try {
        localStorage.setItem(KEY, JSON.stringify(places));
    } catch {
        // storage blocked (private window): the dashboard still works, it just forgets on reload
    }
}

function emptySlots() {
    return Array.from({ length: SLOTS }, () => null);
}

function isPlace(place) {
    return Boolean(place)
        && typeof place.name === 'string'
        && Number.isFinite(place.latitude)
        && Number.isFinite(place.longitude);
}
