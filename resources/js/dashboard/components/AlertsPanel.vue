<script setup>
import { ref } from 'vue';
import { acknowledgeAlert } from '../api';
import { fmt1, fmtDateTime } from '../format';
import ChartPanel from './ChartPanel.vue';

const props = defineProps({
    alerts: { type: Array, required: true },
});

const emit = defineEmits(['acknowledged']);
const busy = ref(null);
const message = ref('');

async function acknowledge(alert) {
    busy.value = alert.id;
    message.value = '';
    try {
        const result = await acknowledgeAlert(alert.id);
        emit('acknowledged', result);
        message.value = `${alert.condition} in ${alert.region} acknowledged.`;
    } catch (error) {
        message.value = error.message;
    } finally {
        busy.value = null;
    }
}

const ruleText = (a) => (a.rule_type === 'statistical' ? `above ${fmt1(a.limit)} (usual ${fmt1(a.expected)})` : `threshold ${fmt1(a.limit)}`);
</script>

<template>
    <ChartPanel id="alerts" title="Alerts, last 8 weeks" subtitle="Raised automatically when a region's weekly count crosses its rule. Acknowledge an alert to show colleagues it is being handled.">
        <p class="visually-hidden" role="status">{{ message }}</p>
        <p v-if="!alerts.length" class="muted">No alerts in this period.</p>
        <div v-else class="table-scroll">
            <table class="data-table alerts-table">
                <caption class="visually-hidden">Alerts</caption>
                <thead>
                    <tr>
                        <th scope="col">Severity</th><th scope="col">Week</th><th scope="col">Condition</th><th scope="col">Region</th>
                        <th scope="col" class="num">Cases</th><th scope="col">Rule</th><th scope="col">Email</th><th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in alerts" :key="a.id">
                        <td><span class="badge" :class="`badge-${a.severity}`">{{ a.severity === 'critical' ? '▲ Critical' : '● Warning' }}</span></td>
                        <td>{{ a.week_label }}</td>
                        <th scope="row">{{ a.condition }} <span class="small muted">{{ a.icd10 }}</span></th>
                        <td>{{ a.region }}</td>
                        <td class="num">{{ a.observed }}</td>
                        <td class="small">{{ ruleText(a) }}</td>
                        <td class="small">{{ a.emailed_at ? `Sent ${fmtDateTime(a.emailed_at)}` : a.email_skipped_reason }}</td>
                        <td>
                            <span v-if="a.status === 'acknowledged'" class="small">Acknowledged by {{ a.acknowledged_by }}<br>{{ fmtDateTime(a.acknowledged_at) }}</span>
                            <button v-else type="button" class="button button-small" :disabled="busy === a.id" @click="acknowledge(a)">
                                Acknowledge<span class="visually-hidden"> {{ a.condition }} in {{ a.region }}, {{ a.week_label }}</span>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </ChartPanel>
</template>
