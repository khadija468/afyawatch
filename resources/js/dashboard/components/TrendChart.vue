<script setup>
import { computed } from 'vue';
import { zeroAxis } from '../axes';
import { fmt1, fmtCount, fmtInt } from '../format';
import { colours } from '../palette';
import ChartCanvas from './ChartCanvas.vue';
import ChartPanel from './ChartPanel.vue';

const props = defineProps({
    trend: { type: Array, required: true },
    condition: { type: Object, required: true },
    scopeLabel: { type: String, required: true },
});

const data = computed(() => ({
    labels: props.trend.map((w) => w.label),
    datasets: [
        {
            label: 'Weekly cases',
            data: props.trend.map((w) => w.cases),
            borderColor: colours.blue,
            backgroundColor: 'rgba(0, 114, 178, 0.12)',
            fill: 'origin',
            pointStyle: 'circle',
            pointRadius: 3,
            borderWidth: 2,
            spanGaps: false,
        },
        {
            label: '8-week average',
            data: props.trend.map((w) => w.rolling_mean),
            borderColor: colours.vermillion,
            borderDash: [6, 4],
            pointStyle: 'line',
            pointRadius: 0,
            borderWidth: 2,
        },
    ],
}));

const options = computed(() => ({
    interaction: { mode: 'index', intersect: false },
    scales: {
        y: zeroAxis('Cases per week'),
        x: { title: { display: true, text: 'ISO week' } },
    },
    plugins: {
        tooltip: {
            callbacks: {
                footer: (items) => {
                    const week = props.trend[items[0].dataIndex];
                    const lines = [`Reports received: ${week.reporting} of ${week.expected} facilities`];
                    if (week.small) lines.push('Small number (fewer than 5): interpret with care');
                    return lines;
                },
            },
        },
    },
}));

const total = computed(() => props.trend.reduce((sum, w) => sum + (w.cases ?? 0), 0));
const label = computed(
    () => `Line chart of weekly ${props.condition.name.toLowerCase()} cases in ${props.scopeLabel}, ${total.value} cases over ${props.trend.length} weeks. A table of the values follows.`,
);
</script>

<template>
    <ChartPanel id="trend" :title="`Weekly ${condition.name.toLowerCase()} cases`" :subtitle="`${scopeLabel} · solid line: reported cases; dashed line: average of the previous 8 weeks`" note="Gaps mean no facility reported that week. ‡ marks counts from 1 to 4.">
        <ChartCanvas type="line" :data="data" :options="options" :label="label" />
        <template #table>
            <table class="data-table">
                <caption class="visually-hidden">Weekly cases</caption>
                <thead><tr><th scope="col">Week</th><th scope="col" class="num">Cases</th><th scope="col" class="num">Deaths</th><th scope="col" class="num">8-week average</th><th scope="col" class="num">Reports received</th></tr></thead>
                <tbody>
                    <tr v-for="w in trend" :key="w.week">
                        <th scope="row">{{ w.label }}</th>
                        <td class="num">{{ fmtCount(w.cases) }}</td>
                        <td class="num">{{ fmtInt(w.deaths) }}</td>
                        <td class="num">{{ fmt1(w.rolling_mean) }}</td>
                        <td class="num">{{ w.reporting }} / {{ w.expected }}</td>
                    </tr>
                </tbody>
            </table>
        </template>
    </ChartPanel>
</template>
