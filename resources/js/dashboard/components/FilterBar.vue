<script setup>
import { computed } from 'vue';

const props = defineProps({
    filters: { type: Object, required: true },
    conditions: { type: Array, required: true },
    regions: { type: Array, required: true },
    facilities: { type: Array, required: true },
    weeks: { type: Array, required: true },
    regionLocked: { type: Boolean, default: false },
});

const emit = defineEmits(['update', 'reset']);

const facilityOptions = computed(() =>
    props.filters.region_id ? props.facilities.filter((f) => f.region_id === props.filters.region_id) : [],
);

const levelLabel = { hospital: 'hospital', health_centre: 'health centre', dispensary: 'dispensary' };

function set(key, raw) {
    const value = raw === '' ? null : ['from', 'to'].includes(key) ? raw : Number(raw);
    const patch = { [key]: value };
    if (key === 'region_id') {
        patch.facility_id = null;
    }
    if (key === 'from' && value > props.filters.to) {
        patch.to = value;
    }
    if (key === 'to' && value < props.filters.from) {
        patch.from = value;
    }
    emit('update', patch);
}

function lastWeeks(n) {
    const values = props.weeks.map((w) => w.value);
    emit('update', { from: values[Math.max(0, values.length - n)], to: values[values.length - 1] });
}
</script>

<template>
    <form class="filter-bar panel" aria-label="Dashboard filters" @submit.prevent>
        <div class="field">
            <label for="f-condition">Condition</label>
            <select id="f-condition" :value="filters.condition_id" @change="set('condition_id', $event.target.value)">
                <option v-for="c in conditions" :key="c.id" :value="c.id">{{ c.name }} ({{ c.icd10_code }})</option>
            </select>
        </div>

        <div class="field">
            <label for="f-region">Region</label>
            <select id="f-region" :value="filters.region_id ?? ''" :disabled="regionLocked" :aria-describedby="regionLocked ? 'f-region-help' : undefined" @change="set('region_id', $event.target.value)">
                <option v-if="!regionLocked" value="">All regions</option>
                <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
            <p v-if="regionLocked" id="f-region-help" class="small muted">Your account covers this region only.</p>
        </div>

        <div class="field">
            <label for="f-facility">Facility</label>
            <select id="f-facility" :value="filters.facility_id ?? ''" :disabled="!filters.region_id" :aria-describedby="!filters.region_id ? 'f-facility-help' : undefined" @change="set('facility_id', $event.target.value)">
                <option value="">All facilities</option>
                <option v-for="f in facilityOptions" :key="f.id" :value="f.id">{{ f.name }} ({{ levelLabel[f.level] }})</option>
            </select>
            <p v-if="!filters.region_id" id="f-facility-help" class="small muted">Choose a region first.</p>
        </div>

        <div class="field">
            <label for="f-from">From week</label>
            <select id="f-from" :value="filters.from" @change="set('from', $event.target.value)">
                <option v-for="w in weeks" :key="w.value" :value="w.value">{{ w.label }}</option>
            </select>
        </div>

        <div class="field">
            <label for="f-to">To week</label>
            <select id="f-to" :value="filters.to" @change="set('to', $event.target.value)">
                <option v-for="w in weeks" :key="w.value" :value="w.value">{{ w.label }}</option>
            </select>
        </div>

        <div class="field field-actions">
            <span class="field-label" id="quick-range-label">Quick range</span>
            <div class="button-row" role="group" aria-labelledby="quick-range-label">
                <button type="button" class="button button-secondary button-small" @click="lastWeeks(4)">4 weeks</button>
                <button type="button" class="button button-secondary button-small" @click="lastWeeks(12)">12 weeks</button>
                <button type="button" class="button button-secondary button-small" @click="lastWeeks(26)">26 weeks</button>
                <button type="button" class="button button-ghost button-small" @click="emit('reset')">Reset</button>
            </div>
        </div>
    </form>
</template>
