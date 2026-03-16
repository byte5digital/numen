<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    score: { type: Number, default: null },
    weight: { type: Number, default: null },
});

const barColor = computed(() => {
    if (props.score === null) return 'bg-gray-600';
    if (props.score >= 90) return 'bg-emerald-500';
    if (props.score >= 75) return 'bg-blue-500';
    if (props.score >= 60) return 'bg-amber-500';
    if (props.score >= 40) return 'bg-orange-500';
    return 'bg-red-500';
});

const displayScore = computed(() =>
    props.score !== null ? Math.round(props.score) : '—',
);

const barWidth = computed(() =>
    props.score !== null ? `${Math.max(0, Math.min(100, props.score))}%` : '0%',
);
</script>

<template>
    <div class="flex flex-col gap-1">
        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-300">{{ label }}</span>
            <div class="flex items-center gap-2">
                <span v-if="weight !== null" class="text-xs text-gray-500">{{ Math.round(weight * 100) }}%</span>
                <span class="font-medium" :class="score !== null ? 'text-white' : 'text-gray-500'">
                    {{ displayScore }}
                </span>
            </div>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-700">
            <div
                class="h-full rounded-full transition-all duration-500 ease-out"
                :class="barColor"
                :style="{ width: barWidth }"
            />
        </div>
    </div>
</template>
