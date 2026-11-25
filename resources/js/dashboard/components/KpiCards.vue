<script setup>
import { computed } from 'vue';
import { fmt2, fmtInt, fmtPct, isSmall } from '../format';

const props = defineProps({
    kpis: { type: Object, required: true },
    filters: { type: Object, required: true },
    condition: { type: Object, required: true },
});

const completenessLow = computed(
    () => props.kpis.completeness.pct !== null && props.kpis.completeness.pct < props.kpis.completeness.target,
);
</script>

<template>
    <section class="kpi-grid" aria-label="Headline figures">
        <article class="kpi">
            <h2 class="kpi-label">{{ condition.name }} cases</h2>
            <p class="kpi-value">
                {{ fmtInt(kpis.cases) }}<span v-if="isSmall(kpis.cases)" class="small-flag" title="Small number">‡</span>
            </p>
            <p class="kpi-context">{{ filters.weeks }} weeks, {{ filters.from_label }} to {{ filters.to_label }} · {{ condition.case_basis }} cases</p>
        </article>

        <article class="kpi">
            <h2 class="kpi-label">Incidence per 100,000</h2>
            <p class="kpi-value">{{ kpis.incidence_per_100k === null ? 'n/a' : fmt2(kpis.incidence_per_100k) }}</p>
            <p v-if="kpis.population" class="kpi-context">{{ fmtInt(kpis.cases) }} {{ kpis.cases === 1 ? 'case' : 'cases' }} ÷ {{ fmtInt(kpis.population) }} people (2022 census), not annualised</p>
            <p v-else class="kpi-context">Not shown for a single facility: its catchment population is unknown.</p>
        </article>

        <article class="kpi">
            <h2 class="kpi-label">Case fatality</h2>
            <p class="kpi-value">{{ kpis.cfr_pct === null ? 'n/a' : fmtPct(kpis.cfr_pct) }}</p>
            <p v-if="kpis.cfr_pct !== null" class="kpi-context">{{ fmtInt(kpis.deaths) }} deaths ÷ {{ fmtInt(kpis.cases) }} cases</p>
            <p v-else class="kpi-context">Too few cases to calculate reliably (needs 20 or more; {{ fmtInt(kpis.deaths) }} {{ kpis.deaths === 1 ? 'death' : 'deaths' }} reported).</p>
        </article>

        <article class="kpi" :class="{ 'kpi-warning': completenessLow }">
            <h2 class="kpi-label">Reporting completeness</h2>
            <p class="kpi-value">{{ fmtPct(kpis.completeness.pct) }}</p>
            <p class="kpi-context">
                {{ fmtInt(kpis.completeness.received) }} of {{ fmtInt(kpis.completeness.expected) }} weekly reports received · target {{ kpis.completeness.target }}%
                <strong v-if="completenessLow"> (below target: low counts may reflect missing reports)</strong>
            </p>
        </article>

        <article class="kpi">
            <h2 class="kpi-label">MR1 coverage, last 12 months</h2>
            <p class="kpi-value">{{ fmtPct(kpis.mr1_coverage.pct) }}</p>
            <p class="kpi-context">{{ fmtInt(kpis.mr1_coverage.doses) }} doses ÷ {{ fmtInt(kpis.mr1_coverage.target) }} estimated surviving infants</p>
        </article>

        <article class="kpi" :class="{ 'kpi-alert': kpis.open_alerts > 0 }">
            <h2 class="kpi-label">Open alerts</h2>
            <p class="kpi-value">{{ fmtInt(kpis.open_alerts) }}</p>
            <p class="kpi-context">All conditions, not yet acknowledged · <a href="#alerts-title">see alerts</a></p>
        </article>
    </section>
</template>
