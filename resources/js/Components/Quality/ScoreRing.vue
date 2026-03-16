<script setup>
import { computed } from 'vue';

const props = defineProps({
    score: { type: Number, default: null },
    size: { type: Number, default: 80 },
    strokeWidth: { type: Number, default: 8 },
    label: { type: String, default: '' },
});

const radius = computed(() => (props.size - props.strokeWidth) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const dashOffset = computed(() => {
    if (props.score === null) return circumference.value;
    const pct = Math.max(0, Math.min(100, props.score)) / 100;
    return circumference.value * (1 - pct);
});

const scoreColor = computed(() => {
    if (props.score === null) return '#6b7280'; // gray-500
    if (props.score >= 90) return '#10b981'; // emerald-500
    if (props.score >= 75) return '#3b82f6'; // blue-500
    if (props.score >= 60) return '#f59e0b'; // amber-500
    if (props.score >= 40) return '#f97316'; // orange-500
    return '#ef4444'; // red-500
});

const displayScore = computed(() =>
    props.score !== null ? Math.round(props.score) : '—',
);
</script>

<template>
    <div class="flex flex-col items-center gap-1">
        <svg
            :width="size"
            :height="size"
            :viewBox="`0 0 ${size} ${size}`"
            class="-rotate-90"
        >
            <!-- Background track -->
            <circle
                :cx="size / 2"
                :cy="size / 2"
                :r="radius"
                fill="none"
                stroke="#374151"
                :stroke-width="strokeWidth"
            />
            <!-- Score arc -->
            <circle
                :cx="size / 2"
                :cy="size / 2"
                :r="radius"
                fill="none"
                :stroke="scoreColor"
                :stroke-width="strokeWidth"
                stroke-linecap="round"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="dashOffset"
                class="transition-all duration-700 ease-out"
            />
        </svg>
        <!-- Score number (overlaid via absolute) -->
        <div
            class="relative -mt-1 flex flex-col items-center"
            :style="{ marginTop: `-${size * 0.6}px` }"
        >
            <span
                class="font-bold leading-none"
                :class="[size >= 80 ? 'text-xl' : 'text-sm']"
                :style="{ color: scoreColor }"
            >{{ displayScore }}</span>
            <span v-if="label" class="mt-0.5 text-xs text-gray-400">{{ label }}</span>
        </div>
        <!-- Spacer to push content below the SVG -->
        <div :style="{ height: `${size * 0.4}px` }" />
    </div>
</template>
