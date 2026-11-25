<script setup>
import { computed } from 'vue';
import { zeroAxis } from '../axes';
import { fmtInt, fmtMonth, fmtPct } from '../format';
import { colours } from '../palette';
import ChartCanvas from './ChartCanvas.vue';
import ChartPanel from './ChartPanel.vue';

const props = defineProps({
    coverage: { type: Object, required: true },
});

const months = computed(() => props.coverage.months);
const highest = computed(() => Math.max(100, ...months.value.flatMap((m) => [m.MR1.pct ?? 0, m.Penta3.pct ?? 0])));

const data = computed(() => ({
    labels: months.value.map((m) => fmtMonth(m.month)),
    datasets: [
        {
            type: 'bar',
            label: 'MR1 (measles-rubella, dose 1)',
            data: months.value.map((m) => m.MR1.pct),
            backgroundColor: colours.blue,
            borderRadius: 2,
        },
        {
            type: 'bar',
            label: 'Penta3',
            data: months.value.map((m) => m.Penta3.pct),
            backgroundColor: colours.green,
            borderRadius: 2,
        },
        {
            type: 'line',
            label: `Target ${props.coverage.target_pct}%`,
            data: months.value.map(() => props.coverage.target_pct),
            borderColor: colours.ink,
            borderDash: [6, 4],
            borderWidth: 2,
            pointRadius: 0,
            pointStyle: 'line',
        },
    ],
}));

const options = computed(() => ({
    interaction: { mode: 'index', intersect: false },
    scales: {
        y: zeroAxis('Coverage (%)', { max: Math.ceil(highest.value / 10) * 10, ticks: { callback: (v) => `${v}%` } }),
    },
    plugins: {
        tooltip: {
            callbacks: {
                label: (item) => {
                    const m = months.value[item.dataIndex];
                    if (item.datasetIndex === 2) return `Target: ${props.coverage.target_pct}%`;
                    const v = item.datasetIndex === 0 ? m.MR1 : m.Penta3;
                    return `${item.dataset.label}: ${fmtPct(v.pct)} (${fmtInt(v.doses)} doses ÷ ${fmtInt(m.target)} infants)`;
                },
            },
        },
    },
}));

const label = computed(
    () => `Bar chart of monthly MR1 and Penta3 coverage in ${props.coverage.scope_label} against a ${props.coverage.target_pct}% target. Twelve-month MR1 coverage ${fmtPct(props.coverage.totals.MR1.pct)}, Penta3 ${fmtPct(props.coverage.totals.Penta3.pct)}. A table follows.`,
);
</script>

<template>
    <ChartPanel
        id="coverage"
        title="Immunization coverage by month"
        :subtitle="`${coverage.scope_label} · doses given to children under 1 ÷ estimated surviving infants`"
        :note="`${coverage.facility_fallback ? 'Facility catchments are unknown, so this shows the whole region. ' : ''}Denominator: 3.5% of the 2022 census population, an assumption. Values above 100% usually mean the denominator is too low.`"
    >
        <ChartCanvas type="bar" :data="data" :options="options" :label="label" />
        <template #table>
            <table class="data-table">
                <caption class="visually-hidden">Monthly coverage</caption>
                <thead><tr><th scope="col">Month</th><th scope="col" class="num">Target infants</th><th scope="col" class="num">MR1 doses</th><th scope="col" class="num">MR1 %</th><th scope="col" class="num">Penta3 doses</th><th scope="col" class="num">Penta3 %</th></tr></thead>
                <tbody>
                    <tr v-for="m in months" :key="m.month">
                        <th scope="row">{{ fmtMonth(m.month) }}</th>
                        <td class="num">{{ fmtInt(m.target) }}</td>
                        <td class="num">{{ fmtInt(m.MR1.doses) }}</td>
                        <td class="num">{{ fmtPct(m.MR1.pct) }}</td>
                        <td class="num">{{ fmtInt(m.Penta3.doses) }}</td>
                        <td class="num">{{ fmtPct(m.Penta3.pct) }}</td>
                    </tr>
                </tbody>
            </table>
        </template>
    </ChartPanel>
</template>
