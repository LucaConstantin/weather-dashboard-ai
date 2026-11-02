<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { getJson } from '../lib/api';
import { placeDetail } from '../lib/places';
import WeatherCard from './WeatherCard.vue';

const props = defineProps({
    index: { type: Number, required: true },
    place: { type: Object, default: null },
    // ids of the places in the other slots, so the same place can't be added twice
    takenIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['pick', 'remove']);

const replacing = ref(false);
const showSearch = computed(() => !props.place || replacing.value);
const input = ref(null);

// ---- search
const query = ref('');
const results = ref([]);
const searchMessage = ref('');
const searchBusy = ref(false);
let searchTimer;
let searchRun = 0;

function onInput() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(runSearch, 350);
}

async function runSearch() {
    clearTimeout(searchTimer);
    const q = query.value.trim();
    if (q.length < 2) {
        results.value = [];
        searchMessage.value = '';
        return;
    }

    const run = ++searchRun;
    searchBusy.value = true;
    const response = await getJson(`/api/places?q=${encodeURIComponent(q)}`);
    if (run !== searchRun) {
        return; // the user kept typing, a newer search owns the list
    }
    searchBusy.value = false;

    results.value = response.ok ? response.data.places : [];
    searchMessage.value = response.ok ? (response.data.message ?? '') : response.message;
}

const isTaken = (result) => props.takenIds.includes(result.id);

function pick(result) {
    const { id, name, region, country, country_code, latitude, longitude } = result;
    emit('pick', { id, name, region, country, country_code, latitude, longitude });
    closeSearch();
}

async function startReplace() {
    replacing.value = true;
    await nextTick();
    input.value?.focus();
}

function closeSearch() {
    replacing.value = false;
    query.value = '';
    results.value = [];
    searchMessage.value = '';
}

// ---- weather
const weather = ref(null);
const weatherError = ref('');
const weatherBusy = ref(false);
let weatherRun = 0;
let refreshTimer;

async function loadWeather() {
    clearTimeout(refreshTimer);
    const place = props.place;
    if (!place) {
        weather.value = null;
        weatherError.value = '';
        return;
    }

    const run = ++weatherRun;
    weatherBusy.value = true;
    weatherError.value = '';
    const response = await getJson(`/api/weather?latitude=${place.latitude}&longitude=${place.longitude}`);
    if (run !== weatherRun) {
        return; // the place was replaced while this was loading
    }
    weatherBusy.value = false;

    if (response.ok) {
        weather.value = response.data;
    } else {
        weather.value = null;
        weatherError.value = response.message;
    }
    refreshTimer = setTimeout(loadWeather, 10 * 60 * 1000);
}

watch(() => props.place && `${props.place.latitude},${props.place.longitude}`, () => {
    weather.value = null;
    loadWeather();
}, { immediate: true });

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    clearTimeout(refreshTimer);
});
</script>

<template>
    <div v-if="showSearch" class="flex h-full min-h-72 flex-col rounded-2xl border border-dashed border-slate-300 bg-white p-4">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium uppercase tracking-wide text-slate-400">Place {{ index + 1 }}</span>
            <button v-if="replacing" type="button" class="text-xs text-slate-500 hover:text-slate-800" @click="closeSearch">Cancel</button>
        </div>

        <form class="mt-3" @submit.prevent="runSearch">
            <label :for="`place-search-${index}`" class="sr-only">Search for a place</label>
            <input
                :id="`place-search-${index}`"
                ref="input"
                v-model="query"
                type="search"
                autocomplete="off"
                placeholder="Type a place, e.g. Springfield"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-200 focus:outline-none"
                @input="onInput"
            >
        </form>

        <p v-if="searchBusy" class="mt-3 text-sm text-slate-500">Searching…</p>
        <p v-else-if="searchMessage" class="mt-3 text-sm text-amber-700" role="alert">{{ searchMessage }}</p>

        <ul v-if="results.length" class="mt-2 max-h-64 divide-y divide-slate-100 overflow-y-auto">
            <li v-for="result in results" :key="result.id">
                <button
                    type="button"
                    class="w-full rounded-md px-2 py-2 text-left hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="isTaken(result)"
                    @click="pick(result)"
                >
                    <span class="block text-sm font-medium">{{ result.name }}</span>
                    <span class="block text-xs text-slate-500">
                        {{ placeDetail(result) || 'No region given' }}
                        <template v-if="result.population"> · {{ result.population.toLocaleString('en-US') }} people</template>
                        <template v-if="isTaken(result)"> · already on the dashboard</template>
                    </span>
                </button>
            </li>
        </ul>

        <p v-if="!query && !results.length" class="mt-auto pt-6 text-sm text-slate-400">
            {{ replacing ? `Search for a place to replace ${place.name}.` : 'Empty. Search for a place to add it.' }}
        </p>
    </div>

    <WeatherCard
        v-else
        class="min-h-72"
        :place="place"
        :weather="weather"
        :loading="weatherBusy"
        :error="weatherError"
        @retry="loadWeather"
        @replace="startReplace"
        @remove="emit('remove')"
    />
</template>
