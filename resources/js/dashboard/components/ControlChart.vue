<script setup>
import { computed } from 'vue';
import { zeroAxis } from '../axes';
import { fmt1, fmtCount } from '../format';
import { colours } from '../palette';
import ChartCanvas from './ChartCanvas.vue';
import ChartPanel from './ChartPanel.vue';

const props = defineProps({
    control: { type: Object, required: true },
    condition: { type: Object, required: true },
});

const points = computed(() => props.control.points);
const signals = computed(() => points.value.filter((p) => p.above_ucl || p.in_run));
const flat = (value) => points.value.map(() => value);

// Signal points differ in shape and size as well as colour.
const pointStyle = computed(() => points.value.map((p) => (p.above_ucl ? 'triangle' : p.in_run ? 'rectRot' : 'circle')));
const pointRadius = computed(() => points.value.map((p) => (p.above_ucl || p.in_run ? 7 : 3)));
const pointColour = computed(() => points.value.map((p) => (p.above_ucl || p.in_run ? colours.vermillion : colours.blue)));

const data = computed(() => ({
    labels: points.value.map((p) => p.label),
    datasets: [
        {
            label: 'Weekly cases',
            data: points.value.map((p) => p.cases),
            borderColor: colours.blue,
            pointStyle: pointStyle.value,
            pointRadius: pointRadius.value,
            pointBackgroundColor: pointColour.value,
            pointBorderColor: pointColour.value,
            borderWidth: 2,
        },
        {
            label: `Upper control limit (${fmt1(props.control.ucl)})`,
            data: flat(props.control.ucl),
            borderColor: colours.vermillion,
            borderDash: [2, 3],
            pointRadius: 0,
            pointStyle: 'line',
            borderWidth: 2,
        },
        {
            label: `Centre line, baseline mean (${fmt1(props.control.centre)})`,
            data: flat(props.control.centre),
            borderColor: colours.ink,
            borderDash: [8, 4],
            pointRadius: 0,
            pointStyle: 'line',
            borderWidth: 1.5,
        },
        {
            label: `Lower control limit (${fmt1(props.control.lcl)})`,
            data: flat(props.control.lcl),
            borderColor: colours.muted,
            borderDash: [2, 3],
            pointRadius: 0,
            pointStyle: 'line',
            borderWidth: 1.5,
        },
    ],
}));

const options = computed(() => ({
    interaction: { mode: 'index', intersect: false },
    scales: { y: zeroAxis('Cases per week'), x: { title: { display: true, text: 'ISO week' } } },
    plugins: {
        tooltip: {
            callbacks: {
                footer: (items) => {
                    const p = points.value[items[0].dataIndex];
                    if (p.above_ucl) return 'Signal: above the upper control limit';
                    if (p.in_run) return 'Signal: 8 or more weeks in a row above the centre line';
                    return '';
                },
            },
        },
    },
}));

const baselineText = computed(() => {
    const b = props.control.baseline;
    return b.from_selected_range
        ? `Too little earlier data, so limits use the selected weeks themselves (${b.weeks} weeks).`
        : `Limits from the baseline ${b.from_label} to ${b.to_label} (${b.weeks} weeks): centre ± 3 × √centre.`;
});

const label = computed(
    () => `Control chart of weekly ${props.condition.name.toLowerCase()} cases with centre line ${fmt1(props.control.centre)} and upper limit ${fmt1(props.control.ucl)}. ${signals.value.length} weeks show a signal. A table follows.`,
);
</script>

<template>
    <ChartPanel id="control" title="Control chart (c-chart)" :subtitle="baselineText" note="Triangles mark weeks above the upper limit; diamonds mark 8 or more weeks in a row above the centre line. Seasonal diseases such as malaria can signal at the start of the season, so read signals alongside the trend.">
        <p class="signal-summary" :class="{ 'has-signal': signals.length }">
            <strong>{{ signals.length ? `${signals.length} week${signals.length > 1 ? 's' : ''} signal unusual activity` : 'No signal: variation is within the expected range' }}</strong>
        </p>
        <ChartCanvas type="line" :data="data" :options="options" :label="label" />
        <template #table>
            <table class="data-table">
                <caption class="visually-hidden">Control chart values</caption>
                <thead><tr><th scope="col">Week</th><th scope="col" class="num">Cases</th><th scope="col">Signal</th></tr></thead>
                <tbody>
                    <tr v-for="p in points" :key="p.week">
                        <th scope="row">{{ p.label }}</th>
                        <td class="num">{{ fmtCount(p.cases) }}</td>
                        <td>{{ p.above_ucl ? 'Above upper limit' : p.in_run ? 'Run above centre' : 'None' }}</td>
                    </tr>
                </tbody>
            </table>
        </template>
    </ChartPanel>
</template>
