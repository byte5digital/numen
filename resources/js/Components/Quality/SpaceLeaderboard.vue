<script setup>
import ScoreRing from './ScoreRing.vue';

defineProps({
    leaderboard: { type: Array, default: () => [] },
});

function scoreColor(score) {
    if (score >= 90) return 'text-emerald-400';
    if (score >= 75) return 'text-blue-400';
    if (score >= 60) return 'text-amber-400';
    if (score >= 40) return 'text-orange-400';
    return 'text-red-400';
}
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-gray-700 bg-gray-800">
        <div class="border-b border-gray-700 px-4 py-3">
            <h3 class="text-sm font-semibold text-gray-200">Top-Scoring Content</h3>
        </div>
        <div v-if="leaderboard.length === 0" class="px-4 py-6 text-center text-sm text-gray-500">
            No scored content yet.
        </div>
        <ul v-else class="divide-y divide-gray-700">
            <li
                v-for="(item, i) in leaderboard"
                :key="item.score_id"
                class="flex items-center gap-4 px-4 py-3 hover:bg-gray-750"
            >
                <span class="w-5 shrink-0 text-center text-xs text-gray-500">{{ i + 1 }}</span>
                <ScoreRing :score="item.overall_score" :size="44" :stroke-width="5" />
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-200">
                        {{ item.title ?? item.content_id }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500">
                        {{ item.scored_at ? new Date(item.scored_at).toLocaleDateString() : '' }}
                    </p>
                </div>
                <span class="shrink-0 text-sm font-bold" :class="scoreColor(item.overall_score)">
                    {{ Math.round(item.overall_score) }}
                </span>
            </li>
        </ul>
    </div>
</template>
