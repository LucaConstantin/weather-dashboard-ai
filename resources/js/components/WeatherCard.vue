<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { placeDetail, weatherIcon } from '../lib/places';

const props = defineProps({
    place: { type: Object, required: true },
    weather: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
});

defineEmits(['replace', 'remove', 'retry']);

// the local time keeps moving while the page is open
const now = ref(Date.now());
let clock;
onMounted(() => { clock = setInterval(() => { now.value = Date.now(); }, 15000); });
onBeforeUnmount(() => clearInterval(clock));

const localTime = computed(() => {
    if (!props.weather) {
        return '';
    }
    const options = { weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' };
    try {
        return new Intl.DateTimeFormat('en-GB', { ...options, timeZone: props.weather.timezone }).format(now.value);
    } catch {
        // unknown time zone name: fall back to the fixed offset Open-Meteo gave us
        const shifted = new Date(now.value + props.weather.utc_offset_seconds * 1000);
        return new Intl.DateTimeFormat('en-GB', { ...options, timeZone: 'UTC' }).format(shifted);
    }
});

const icon = computed(() => (props.weather ? weatherIcon(props.weather.kind, props.weather.is_day) : ''));
const round = (value) => Math.round(value);
</script>

<template>
    <div class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="truncate text-lg font-semibold">{{ place.name }}</h2>
                <p class="truncate text-xs text-slate-500">{{ placeDetail(place) }}</p>
            </div>
            <p v-if="weather" class="shrink-0 text-right text-sm tabular-nums text-slate-600">
                {{ localTime }}
                <span class="block text-[11px] text-slate-400">local time</span>
            </p>
        </div>

        <div v-if="weather" class="mt-4">
            <div class="flex items-center gap-3">
                <span class="text-4xl leading-none" aria-hidden="true">{{ icon }}</span>
                <div>
                    <p class="text-4xl font-semibold tabular-nums">
                        {{ round(weather.temperature) }}<span class="align-top text-lg text-slate-500">{{ weather.units.temperature }}</span>
                    </p>
                    <p class="text-sm text-slate-600">{{ weather.condition }}</p>
                </div>
            </div>
            <dl class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                <div class="rounded-lg bg-slate-50 p-2">
                    <dt class="text-slate-400">High</dt>
                    <dd class="text-sm font-medium tabular-nums">{{ round(weather.high) }} {{ weather.units.temperature }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-2">
                    <dt class="text-slate-400">Low</dt>
                    <dd class="text-sm font-medium tabular-nums">{{ round(weather.low) }} {{ weather.units.temperature }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-2">
                    <dt class="text-slate-400">Wind</dt>
                    <dd class="text-sm font-medium tabular-nums">{{ round(weather.wind_speed) }} {{ weather.units.wind_speed }}</dd>
                </div>
            </dl>
        </div>

        <p v-else-if="loading" class="mt-6 text-sm text-slate-500">Loading weather…</p>

        <div v-else-if="error" class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800" role="alert">
            {{ error }}
            <button type="button" class="ml-1 font-medium underline" @click="$emit('retry')">Retry</button>
        </div>

        <div class="mt-auto flex gap-2 pt-4">
            <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50" @click="$emit('replace')">Replace</button>
            <button type="button" class="rounded-lg px-3 py-1.5 text-sm text-slate-500 hover:bg-red-50 hover:text-red-700" @click="$emit('remove')">Remove</button>
        </div>
    </div>
</template>
