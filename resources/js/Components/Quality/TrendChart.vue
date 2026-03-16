<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import {
    Chart,
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';

Chart.register(
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Tooltip,
    Legend,
    Filler,
);

const props = defineProps({
    trends: { type: Object, default: () => ({}) },
});

const canvasRef = ref(null);
let chartInstance = null;

const DIMENSION_COLORS = {
    overall: '#818cf8',      // indigo-400
    readability: '#34d399',  // emerald-400
    seo: '#60a5fa',          // blue-400
    brand: '#f472b6',        // pink-400
    factual: '#fb923c',      // orange-400
    engagement: '#facc15',   // yellow-400
};

function buildDatasets() {
    const labels = Object.keys(props.trends).sort();
    const dimensions = ['overall', 'readability', 'seo', 'brand', 'factual', 'engagement'];
    return {
        labels,
        datasets: dimensions.map(dim => ({
            label: dim.charAt(0).toUpperCase() + dim.slice(1),
            data: labels.map(date => props.trends[date]?.[dim] ?? null),
            borderColor: DIMENSION_COLORS[dim],
            backgroundColor: DIMENSION_COLORS[dim] + '22',
            fill: dim === 'overall',
            tension: 0.3,
            pointRadius: 3,
            borderWidth: dim === 'overall' ? 2.5 : 1.5,
            hidden: !['overall', 'readability', 'seo'].includes(dim),
            spanGaps: true,
        })),
    };
}

function createChart() {
    if (!canvasRef.value) return;
    const { labels, datasets } = buildDatasets();
    chartInstance = new Chart(canvasRef.value, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: {
                    grid: { color: '#1f2937' },
                    ticks: { color: '#9ca3af', maxTicksLimit: 10 },
                },
                y: {
                    min: 0,
                    max: 100,
                    grid: { color: '#1f2937' },
                    ticks: { color: '#9ca3af', stepSize: 20 },
                },
            },
            plugins: {
                legend: {
                    labels: { color: '#d1d5db', boxWidth: 12, padding: 16 },
                },
                tooltip: {
                    backgroundColor: '#111827',
                    borderColor: '#374151',
                    borderWidth: 1,
                    titleColor: '#f9fafb',
                    bodyColor: '#d1d5db',
                },
            },
        },
    });
}

onMounted(() => createChart());
onBeforeUnmount(() => chartInstance?.destroy());

watch(() => props.trends, () => {
    if (!chartInstance) return;
    const { labels, datasets } = buildDatasets();
    chartInstance.data.labels = labels;
    chartInstance.data.datasets = datasets;
    chartInstance.update();
}, { deep: true });
</script>

<template>
    <div class="relative h-64 w-full">
        <canvas ref="canvasRef" />
    </div>
</template>
