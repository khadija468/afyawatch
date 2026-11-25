<script setup>
import { computed } from 'vue';
import { zeroAxis } from '../axes';
import { fmt2, fmtCount, fmtInt } from '../format';
import { colours } from '../palette';
import ChartCanvas from './ChartCanvas.vue';
import ChartPanel from './ChartPanel.vue';

const props = defineProps({
    regions: { type: Array, required: true },
    facilities: { type: Array, required: true },
    regionName: { type: String, default: null },
    selectedFacilityId: { type: Number, default: null },
    condition: { type: Object, required: true },
});

const emit = defineEmits(['drill', 'select-facility']);

const byFacility = computed(() => props.facilities.length > 0);

const rows = computed(() => {
    if (byFacility.value) {
        return [...props.facilities].sort((a, b) => b.cases - a.cases);
    }
    return props.regions.filter((r) => r.in_view).sort((a, b) => b.incidence_per_100k - a.incidence_per_100k);
});

const data = computed(() => ({
    labels: rows.value.map((r) => r.name),
    datasets: [
        {
            label: byFacility.value ? 'Cases' : 'Cases per 100,000',
            data: rows.value.map((r) => (byFacility.value ? r.cases : r.incidence_per_100k)),
            backgroundColor: rows.value.map((r) => (byFacility.value && r.id === props.selectedFacilityId ? colours.vermillion : colours.blue)),
            borderRadius: 3,
            maxBarThickness: 22,
        },
    ],
}));

const options = computed(() => ({
    indexAxis: 'y',
    scales: {
        x: zeroAxis(byFacility.value ? 'Cases in the selected weeks' : 'Cases per 100,000 people', byFacility.value ? {} : { ticks: { precision: 2 } }),
        y: { ticks: { autoSkip: false } },
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (item) => {
                    const r = rows.value[item.dataIndex];
                    return byFacility.value
                        ? `${fmtInt(r.cases)} cases, ${r.deaths} deaths; reported ${r.weeks_reported} of ${r.weeks_expected} weeks`
                        : `${fmt2(r.incidence_per_100k)} per 100,000 (${fmtInt(r.cases)} cases ÷ ${fmtInt(r.population)} people)`;
                },
            },
        },
    },
}));

const height = computed(() => Math.max(200, rows.value.length * 26 + 70));

function select(index) {
    const row = rows.value[index];
    if (byFacility.value) {
        emit('select-facility', row.id === props.selectedFacilityId ? null : row.id);
    } else {
        emit('drill', row.id);
    }
}

const title = computed(() => (byFacility.value ? `Facilities in ${props.regionName}` : 'Regions compared'));
const subtitle = computed(() =>
    byFacility.value
        ? `${props.condition.name} cases by facility · select a bar to focus on one facility`
        : `${props.condition.name} cases per 100,000, highest first · select a bar to drill down`,
);
const label = computed(() => {
    const top = rows.value[0];
    if (!top) return 'Bar chart with no data';
    return byFacility.value
        ? `Bar chart of ${props.condition.name.toLowerCase()} cases by facility in ${props.regionName}. Highest: ${top.name}, ${top.cases} cases. A table follows.`
        : `Bar chart of incidence by region. Highest: ${top.name}, ${fmt2(top.incidence_per_100k)} per 100,000. A table follows.`;
});
</script>

<template>
    <ChartPanel id="comparison" :title="title" :subtitle="subtitle" :note="byFacility ? 'Facility catchment populations are unknown, so facilities are compared on counts, not rates.' : 'Bars start at zero so differences are not exaggerated.'">
        <ChartCanvas type="bar" :data="data" :options="options" :label="label" :height="height" @select="select" />
        <template #table>
            <table v-if="byFacility" class="data-table">
                <caption class="visually-hidden">Cases by facility</caption>
                <thead><tr><th scope="col">Facility</th><th scope="col" class="num">Cases</th><th scope="col" class="num">Deaths</th><th scope="col" class="num">Weeks reported</th></tr></thead>
                <tbody>
                    <tr v-for="f in rows" :key="f.id">
                        <th scope="row">{{ f.name }}</th>
                        <td class="num">{{ fmtCount(f.cases) }}</td>
                        <td class="num">{{ f.deaths }}</td>
                        <td class="num">{{ f.weeks_reported }} / {{ f.weeks_expected }}</td>
                    </tr>
                </tbody>
            </table>
            <table v-else class="data-table">
                <caption class="visually-hidden">Incidence by region</caption>
                <thead><tr><th scope="col">Region</th><th scope="col" class="num">Per 100,000</th><th scope="col" class="num">Cases</th></tr></thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.id">
                        <th scope="row">{{ r.name }}</th>
                        <td class="num">{{ fmt2(r.incidence_per_100k) }}</td>
                        <td class="num">{{ fmtCount(r.cases) }}</td>
                    </tr>
                </tbody>
            </table>
        </template>
    </ChartPanel>
</template>
