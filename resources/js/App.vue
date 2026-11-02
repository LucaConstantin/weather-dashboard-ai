<script setup>
import { ref, watch } from 'vue';
import PlaceSlot from './components/PlaceSlot.vue';
import { loadPlaces, savePlaces } from './lib/storage';

const places = ref(loadPlaces());
watch(places, (value) => savePlaces(value), { deep: true });

function setPlace(index, place) {
    places.value[index] = place;
}

const idsOutside = (index) => places.value.filter((place, i) => place && i !== index).map((place) => place.id);
</script>

<template>
    <main class="mx-auto max-w-6xl px-4 py-8 sm:py-12">
        <header class="mb-8">
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">Four places, one picture</h1>
            <p class="mt-1 text-sm text-slate-500">Add four places to see their weather, then turn it into one image.</p>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Places">
            <PlaceSlot
                v-for="(place, index) in places"
                :key="index"
                :index="index"
                :place="place"
                :taken-ids="idsOutside(index)"
                @pick="setPlace(index, $event)"
                @remove="setPlace(index, null)"
            />
        </section>
    </main>
</template>
