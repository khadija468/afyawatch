<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

// Chart.js is fetched as its own file after the page has rendered.
let chartModule = null;
const loadChart = () => (chartModule ??= import('../chart').then((m) => m.Chart));

const props = defineProps({
    type: { type: String, required: true },
    data: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
    label: { type: String, required: true },
    height: { type: Number, default: 280 },
});

const emit = defineEmits(['select']);
const box = ref(null);
const canvas = ref(null);
let chart = null;
let visible = false;
let observer = null;

async function draw() {
    const Chart = await loadChart();
    if (!canvas.value) return; // unmounted while loading
    chart?.destroy();
    chart = new Chart(canvas.value, {
        type: props.type,
        data: props.data,
        options: {
            ...props.options,
            onClick: (event, elements) => {
                if (elements.length) {
                    emit('select', elements[0].index);
                }
            },
        },
    });
}

// Charts below the fold are only drawn when they are about to scroll into view,
// which keeps the first load fast on slow phones.
onMounted(() => {
    if (!('IntersectionObserver' in window)) {
        visible = true;
        draw();
        return;
    }
    observer = new IntersectionObserver((entries) => {
        if (entries.some((e) => e.isIntersecting)) {
            visible = true;
            observer.disconnect();
            draw();
        }
    }, { rootMargin: '200px 0px' });
    observer.observe(box.value);
});

watch(() => [props.data, props.options], () => visible && draw());

onBeforeUnmount(() => {
    observer?.disconnect();
    chart?.destroy();
});
</script>

<template>
    <div ref="box" class="chart-box" :style="{ height: `${height}px` }">
        <canvas ref="canvas" role="img" :aria-label="label"></canvas>
    </div>
</template>
