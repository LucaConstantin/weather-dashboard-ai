<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { postJson } from '../lib/api';

const props = defineProps({
    // the four slots; an empty slot is null
    places: { type: Array, required: true },
});

const missing = computed(() => props.places.filter((place) => !place).length);
const placesKey = computed(() => props.places.map((place) => place?.id ?? '-').join('|'));

const busy = ref(false);
const elapsed = ref(0);
const error = ref('');
const prompt = ref('');
const result = ref(null);
const madeFor = ref('');
let ticker;

async function generate() {
    busy.value = true;
    error.value = '';
    elapsed.value = 0;
    ticker = setInterval(() => { elapsed.value += 1; }, 1000);

    const places = props.places.map(({ name, region, country, latitude, longitude }) => ({ name, region, country, latitude, longitude }));
    const key = placesKey.value;
    const response = await postJson('/api/image', { places }, { timeoutMs: 150000 });

    clearInterval(ticker);
    busy.value = false;

    if (response.data?.prompt) {
        prompt.value = response.data.prompt;
    }
    if (response.ok) {
        result.value = response.data;
        madeFor.value = key;
    } else {
        error.value = response.message;
    }
}

const outdated = computed(() => result.value && madeFor.value !== placesKey.value);
const altText = computed(() => `Generated picture of the weather in ${props.places.filter(Boolean).map((place) => place.name).join(', ')}`);

onBeforeUnmount(() => clearInterval(ticker));
</script>

<template>
    <section class="mt-10" aria-labelledby="image-title">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="image-title" class="text-xl font-semibold">One picture from all four</h2>
                <p class="text-sm text-slate-500">The prompt is built from the weather above and shown next to the image.</p>
            </div>
            <div class="flex items-center gap-3">
                <span v-if="missing" class="text-sm text-slate-500">Add {{ missing }} more {{ missing === 1 ? 'place' : 'places' }} first.</span>
                <span v-else-if="busy" class="text-sm tabular-nums text-slate-500">Painting… {{ elapsed }}s</span>
                <button
                    type="button"
                    class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-sky-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                    :disabled="missing > 0 || busy"
                    @click="generate"
                >
                    {{ result ? 'Generate again' : 'Generate image' }}
                </button>
            </div>
        </div>

        <div v-if="error" class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800" role="alert">{{ error }}</div>
        <p v-if="outdated" class="mt-4 text-sm text-slate-500">The places changed since this image was made.</p>

        <div v-if="result || prompt" class="mt-4 grid gap-4 lg:grid-cols-[2fr_1fr]">
            <figure class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <img v-if="result" :src="result.image" :alt="altText" class="block w-full" :class="{ 'opacity-60': busy }">
                <div v-else class="flex aspect-video items-center justify-center p-6 text-center text-sm text-slate-400">No image yet.</div>
                <figcaption v-if="result" class="border-t border-slate-100 px-3 py-2 text-xs text-slate-400">Made by {{ result.model }}</figcaption>
            </figure>
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <h3 class="text-xs font-medium uppercase tracking-wide text-slate-400">Prompt</h3>
                <pre class="mt-2 whitespace-pre-wrap font-sans text-xs leading-relaxed text-slate-700">{{ prompt }}</pre>
            </div>
        </div>
    </section>
</template>
