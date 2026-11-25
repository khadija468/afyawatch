<script setup>
import { computed } from 'vue';
import { zeroAxis } from '../axes';
import { fmtInt, fmtPct } from '../format';
import { series } from '../palette';
import ChartCanvas from './ChartCanvas.vue';
import ChartPanel from './ChartPanel.vue';

const props = defineProps({
    campaign: { type: Object, required: true },
});

const patterns = ['circle', 'triangle', 'rect', 'rectRot'];

const data = computed(() => ({
    labels: props.campaign.weeks.map((w) => w.label),
    datasets: props.campaign.platforms.map((platform, i) => ({
        label: platform,
        data: props.campaign.weeks.map((w) => w.platforms[platform].engagements),
        backgroundColor: series[i % series.length],
        borderColor: '#ffffff',
        borderWidth: 1,
        pointStyle: patterns[i % patterns.length],
        stack: 'engagements',
    })),
}));

const options = computed(() => ({
    interaction: { mode: 'index', intersect: false },
    scales: {
        x: { stacked: true, title: { display: true, text: 'ISO week' } },
        y: { ...zeroAxis('Engagements (reactions, comments, shares)'), stacked: true },
    },
    plugins: {
        tooltip: {
            callbacks: {
                label: (item) => {
                    const platform = props.campaign.platforms[item.datasetIndex];
                    const week = props.campaign.weeks[item.dataIndex].platforms[platform];
                    const rate = week.reach ? (week.engagements / week.reach) * 100 : null;
                    return `${platform}: ${fmtInt(week.engagements)} engagements, reach ${fmtInt(week.reach)} (${fmtPct(rate)})`;
                },
            },
        },
    },
}));

const t = computed(() => props.campaign.totals);
const label = computed(
    () => `Stacked bar chart of weekly campaign engagements by platform. In total ${fmtInt(t.value.engagements)} engagements from ${fmtInt(t.value.reach)} accounts reached. A table follows.`,
);
</script>

<template>
    <ChartPanel
        id="campaign"
        :title="campaign.name"
        :subtitle="`Social media, ${campaign.period.from} to ${campaign.period.to} · weekly engagements by platform`"
        note="Platform-reported figures (synthetic for this prototype). Reach is counted per platform, so one person reached on two platforms counts twice. No individual profiles are collected."
    >
        <dl class="mini-stats">
            <div><dt>Accounts reached</dt><dd>{{ fmtInt(t.reach) }}</dd></div>
            <div><dt>Engagement rate</dt><dd>{{ fmtPct(t.engagement_rate_pct) }}</dd></div>
            <div><dt>Link clicks to vaccination info</dt><dd>{{ fmtInt(t.link_clicks) }}</dd></div>
            <div><dt>Shares</dt><dd>{{ fmtInt(t.shares) }}</dd></div>
        </dl>
        <ChartCanvas type="bar" :data="data" :options="options" :label="label" />
        <p class="small"><a href="/summary">Open the shareable public summary card</a></p>
        <template #table>
            <table class="data-table">
                <caption class="visually-hidden">Weekly engagements by platform</caption>
                <thead>
                    <tr><th scope="col">Week</th><th v-for="p in campaign.platforms" :key="p" scope="col" class="num">{{ p }}</th></tr>
                </thead>
                <tbody>
                    <tr v-for="w in campaign.weeks" :key="w.week">
                        <th scope="row">{{ w.label }}</th>
                        <td v-for="p in campaign.platforms" :key="p" class="num">{{ fmtInt(w.platforms[p].engagements) }}</td>
                    </tr>
                </tbody>
            </table>
        </template>
    </ChartPanel>
</template>
