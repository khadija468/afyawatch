<script setup>
import { fmtCount } from '../format';
import ChartPanel from './ChartPanel.vue';

defineProps({
    conditions: { type: Array, required: true },
    selectedId: { type: Number, required: true },
    weekLabel: { type: String, required: true },
    scopeLabel: { type: String, required: true },
});

const emit = defineEmits(['select']);

function change(c) {
    if (c.this_week === null || c.last_week === null) return 'no comparison';
    const diff = c.this_week - c.last_week;
    if (diff === 0) return 'no change';
    return `${diff > 0 ? 'up' : 'down'} ${Math.abs(diff)}`;
}
</script>

<template>
    <ChartPanel id="conditions" title="All conditions this week" :subtitle="`${scopeLabel} · ${weekLabel} compared with the week before`">
        <div class="table-scroll">
            <table class="data-table">
                <caption class="visually-hidden">Cases of every condition, latest week against the previous week</caption>
                <thead>
                    <tr><th scope="col">Condition</th><th scope="col">Codes</th><th scope="col" class="num">This week</th><th scope="col" class="num">Last week</th><th scope="col">Change</th></tr>
                </thead>
                <tbody>
                    <tr v-for="c in conditions" :key="c.id" :class="{ 'row-selected': c.id === selectedId }">
                        <th scope="row">
                            <button type="button" class="link-button" :aria-pressed="c.id === selectedId ? 'true' : 'false'" @click="emit('select', c.id)">{{ c.name }}</button>
                        </th>
                        <td class="small"><abbr title="ICD-10">{{ c.icd10 }}</abbr> · <abbr title="SNOMED CT">{{ c.snomed }}</abbr></td>
                        <td class="num">{{ fmtCount(c.this_week) }}</td>
                        <td class="num">{{ fmtCount(c.last_week) }}</td>
                        <td>{{ change(c) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </ChartPanel>
</template>
