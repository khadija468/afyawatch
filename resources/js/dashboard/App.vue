<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { exportUrl, fetchDashboard, fhirUrl, toQuery } from './api';
import AlertsPanel from './components/AlertsPanel.vue';
import CampaignPanel from './components/CampaignPanel.vue';
import ComparisonChart from './components/ComparisonChart.vue';
import ConditionTable from './components/ConditionTable.vue';
import ControlChart from './components/ControlChart.vue';
import CoverageChart from './components/CoverageChart.vue';
import FilterBar from './components/FilterBar.vue';
import KpiCards from './components/KpiCards.vue';
import RegionTileMap from './components/RegionTileMap.vue';
import TrendChart from './components/TrendChart.vue';

const props = defineProps({
    bootstrap: { type: Object, required: true },
});

const boot = props.bootstrap;
const isManager = boot.user.role === 'manager';
const weekValues = boot.weeks.map((w) => w.value);

function initialFilters() {
    const query = new URLSearchParams(window.location.search);
    const pickWeek = (key) => (weekValues.includes(query.get(key)) ? query.get(key) : boot.defaults[key]);
    const pickId = (key, list) => {
        const id = Number(query.get(key));
        return list.some((item) => item.id === id) ? id : null;
    };

    const filters = {
        from: pickWeek('from'),
        to: pickWeek('to'),
        condition_id: pickId('condition_id', boot.conditions) ?? boot.defaults.condition_id,
        region_id: boot.user.region_id ?? pickId('region_id', boot.regions) ?? boot.defaults.region_id,
        facility_id: pickId('facility_id', boot.facilities),
    };

    if (filters.facility_id && boot.facilities.find((f) => f.id === filters.facility_id)?.region_id !== filters.region_id) {
        filters.facility_id = null;
    }
    if (filters.from > filters.to) {
        [filters.from, filters.to] = [filters.to, filters.from];
    }
    return filters;
}

const filters = reactive(initialFilters());
const data = ref(null);
const loading = ref(false);
const error = ref('');
const sessionExpired = ref(false);
let controller = null;

const condition = computed(() => boot.conditions.find((c) => c.id === filters.condition_id) ?? boot.conditions[0]);
const regionName = computed(() => boot.regions.find((r) => r.id === filters.region_id)?.name ?? null);
const statusMessage = computed(() => (loading.value ? 'Updating figures…' : data.value ? `Showing ${condition.value.name}, ${data.value.filters.scope_label}.` : ''));

async function load() {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    error.value = '';

    try {
        data.value = await fetchDashboard({ ...filters }, controller.signal);
    } catch (e) {
        if (e.name === 'AbortError') return;
        error.value = e.message;
        sessionExpired.value = e.status === 401 || e.status === 419;
    } finally {
        loading.value = false;
    }
}

watch(
    () => ({ ...filters }),
    (value) => {
        window.history.replaceState(null, '', `?${toQuery(value)}`);
        load();
    },
);

// Use the figures that came with the page when they match the filters; otherwise ask the API.
const sameFilters = (a, b) => ['from', 'to', 'condition_id', 'region_id', 'facility_id'].every((k) => (a[k] ?? null) === (b[k] ?? null));

onMounted(() => {
    if (boot.initial && sameFilters(boot.initial.filters, filters)) {
        data.value = boot.initial;
    } else {
        load();
    }
});

function update(patch) {
    Object.assign(filters, patch);
}

function reset() {
    Object.assign(filters, {
        from: boot.defaults.from,
        to: boot.defaults.to,
        condition_id: boot.defaults.condition_id,
        region_id: boot.user.region_id ?? null,
        facility_id: null,
    });
}

function drill(regionId) {
    if (!isManager) return;
    update({ region_id: regionId === filters.region_id ? null : regionId, facility_id: null });
}

function onAcknowledged(result) {
    const alert = data.value.alerts.find((a) => a.id === result.id);
    if (alert) {
        Object.assign(alert, result);
        data.value.kpis.open_alerts = data.value.alerts.filter((a) => a.status === 'open').length;
    }
}
</script>

<template>
    <div class="dashboard-heading">
        <div>
            <h1>Disease surveillance dashboard</h1>
            <p class="muted">
                Signed in as {{ boot.user.name }} ·
                {{ isManager ? 'Manager, all regions' : `Clinician, ${boot.user.region_name} region only` }}
            </p>
        </div>
        <div class="heading-actions">
            <a class="button button-secondary button-small" :href="exportUrl(filters)" download>Export CSV (aggregated)</a>
            <a class="button button-ghost button-small" :href="fhirUrl(filters)" target="_blank" rel="noopener">FHIR MeasureReport<span class="visually-hidden"> (opens in a new tab)</span></a>
        </div>
    </div>

    <FilterBar
        :filters="filters"
        :conditions="boot.conditions"
        :regions="boot.regions"
        :facilities="boot.facilities"
        :weeks="boot.weeks"
        :region-locked="!isManager"
        @update="update"
        @reset="reset"
    />

    <p class="visually-hidden" aria-live="polite">{{ statusMessage }}</p>

    <div v-if="error" class="notice notice-error" role="alert">
        {{ error }}
        <a v-if="sessionExpired" href="/login">Sign in again</a>
    </div>

    <div v-if="data" class="dashboard-body" :class="{ 'is-loading': loading }" :aria-busy="loading ? 'true' : 'false'">
        <div class="scope-line">
            <p>
                <strong>{{ condition.name }}</strong> ({{ condition.icd10_code }}) · {{ data.filters.scope_label }} ·
                {{ data.filters.from_label }} to {{ data.filters.to_label }}
            </p>
            <button v-if="isManager && filters.region_id" type="button" class="button button-ghost button-small" @click="update({ region_id: null, facility_id: null })">
                Back to national view
            </button>
        </div>

        <KpiCards :kpis="data.kpis" :filters="data.filters" :condition="condition" />

        <div class="dashboard-grid">
            <TrendChart class="span-2" :trend="data.trend" :condition="condition" :scope-label="data.filters.scope_label" />
            <ControlChart class="span-2" :control="data.control" :condition="condition" />
            <RegionTileMap :regions="data.regions" :selected-region-id="filters.region_id" :condition="condition" :can-drill="isManager" @drill="drill" />
            <ComparisonChart
                :regions="data.regions"
                :facilities="data.facilities"
                :region-name="regionName"
                :selected-facility-id="filters.facility_id"
                :condition="condition"
                @drill="drill"
                @select-facility="(id) => update({ facility_id: id })"
            />
            <CoverageChart :coverage="data.coverage" />
            <ConditionTable :conditions="data.conditions" :selected-id="filters.condition_id" :week-label="data.filters.to_label" :scope-label="data.filters.scope_label" @select="(id) => update({ condition_id: id })" />
            <CampaignPanel v-if="data.campaign" class="span-2" :campaign="data.campaign" />
            <AlertsPanel class="span-2" :alerts="data.alerts" @acknowledged="onAcknowledged" />
        </div>
    </div>

    <p v-else-if="loading" class="loading-block">Loading figures…</p>
</template>
