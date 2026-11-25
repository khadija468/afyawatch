<script setup>
import { ref } from 'vue';

defineProps({
    id: { type: String, required: true },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    note: { type: String, default: '' },
});

// The data table is only built once someone opens it.
const tableOpen = ref(false);
</script>

<template>
    <section class="panel chart-panel" :aria-labelledby="`${id}-title`">
        <header class="panel-header">
            <h2 :id="`${id}-title`">{{ title }}</h2>
            <p v-if="subtitle" class="small muted">{{ subtitle }}</p>
        </header>

        <slot />

        <details v-if="$slots.table" class="data-details" @toggle="tableOpen = $event.target.open">
            <summary>Show the data as a table</summary>
            <div v-if="tableOpen" class="table-scroll">
                <slot name="table" />
            </div>
        </details>

        <p v-if="note" class="small muted panel-note">{{ note }}</p>
    </section>
</template>
