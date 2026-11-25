<script setup>
import { computed } from 'vue';
import { fmt2, fmtCount, fmtInt } from '../format';
import { mapScale } from '../palette';
import ChartPanel from './ChartPanel.vue';

const props = defineProps({
    regions: { type: Array, required: true },
    selectedRegionId: { type: Number, default: null },
    condition: { type: Object, required: true },
    canDrill: { type: Boolean, default: true },
});

const emit = defineEmits(['drill']);

const visible = computed(() => props.regions.filter((r) => r.in_view));
const max = computed(() => Math.max(0, ...visible.value.map((r) => r.incidence_per_100k)));

// A colour scale needs at least two regions to compare; a single region gets a neutral colour.
const comparable = computed(() => visible.value.length > 1 && max.value > 0);

// Enough decimals that small rates do not all round to zero.
const decimals = computed(() => (max.value < 1 ? 2 : max.value < 10 ? 1 : 0));
const fmtRate = (n) => n.toLocaleString('en-GB', { minimumFractionDigits: decimals.value, maximumFractionDigits: decimals.value });

// Five equal-width classes from zero to the highest value shown.
const breaks = computed(() => mapScale.map((_, i) => (max.value * (i + 1)) / mapScale.length));
const step = (value) => {
    if (!comparable.value) return visible.value.length > 1 ? 0 : 2;
    return Math.min(mapScale.length - 1, breaks.value.findIndex((b) => value <= b));
};

const legend = computed(() =>
    mapScale.map((colour, i) => ({
        ...colour,
        text: `${fmtRate(i === 0 ? 0 : breaks.value[i - 1])} to ${fmtRate(breaks.value[i])}`,
    })),
);

function tileStyle(region) {
    const base = { gridColumn: region.tile_col + 1, gridRow: region.tile_row + 1 };
    if (!region.in_view) return base;
    const colour = mapScale[step(region.incidence_per_100k)];
    return { ...base, backgroundColor: colour.fill, color: colour.text };
}

// Extra words for screen readers. The tile's accessible name is its visible text plus these,
// so it always contains what is on screen (WCAG 2.5.3, label in name).
function tileDetail(region) {
    if (!region.in_view) return ' not in your view';
    return ` per 100,000: ${fmtInt(region.cases)} cases, population ${fmtInt(region.population)}`;
}

const sorted = computed(() => [...visible.value].sort((a, b) => b.incidence_per_100k - a.incidence_per_100k));
</script>

<template>
    <ChartPanel id="map" title="Incidence by region" :subtitle="`${condition.name} cases per 100,000 people in the selected weeks · select a region to drill down`" note="Each region is drawn as an equal tile in roughly its real position, so large but sparsely populated regions do not dominate the picture.">
        <div class="tile-map" role="group" aria-label="Tile map of the regions of Tanzania Mainland">
            <button
                v-for="region in regions"
                :key="region.id"
                type="button"
                class="tile"
                :class="{ 'tile-off': !region.in_view, 'tile-selected': region.id === selectedRegionId }"
                :style="tileStyle(region)"
                :disabled="!region.in_view || !canDrill"
                :aria-pressed="region.id === selectedRegionId ? 'true' : 'false'"
                :title="region.name"
                @click="emit('drill', region.id)"
            >
                <span class="tile-name">{{ region.name }}</span>
                <span class="tile-value">{{ region.in_view ? fmtRate(region.incidence_per_100k) : '–' }}</span>
                <span class="visually-hidden">{{ tileDetail(region) }}</span>
            </button>
        </div>

        <ul v-if="comparable" class="map-legend" aria-label="Legend: cases per 100,000">
            <li v-for="item in legend" :key="item.fill">
                <span class="swatch" :style="{ backgroundColor: item.fill }"></span>{{ item.text }}
            </li>
            <li v-if="regions.some((r) => !r.in_view)"><span class="swatch swatch-off"></span>Not in your view</li>
        </ul>
        <p v-else class="small muted map-legend">
            {{ visible.length > 1 ? 'No cases in any region in these weeks.' : 'Only your region is shown, so there is no colour scale to compare against. Hatched tiles are outside your view.' }}
        </p>

        <template #table>
            <table class="data-table">
                <caption class="visually-hidden">Incidence by region</caption>
                <thead><tr><th scope="col">Region</th><th scope="col" class="num">Cases</th><th scope="col" class="num">Population (2022)</th><th scope="col" class="num">Per 100,000</th><th scope="col" class="num">Reports</th></tr></thead>
                <tbody>
                    <tr v-for="r in sorted" :key="r.id">
                        <th scope="row">{{ r.name }}</th>
                        <td class="num">{{ fmtCount(r.cases) }}</td>
                        <td class="num">{{ fmtInt(r.population) }}</td>
                        <td class="num">{{ fmt2(r.incidence_per_100k) }}</td>
                        <td class="num">{{ r.reports_received }} / {{ r.reports_expected }}</td>
                    </tr>
                </tbody>
            </table>
        </template>
    </ChartPanel>
</template>
