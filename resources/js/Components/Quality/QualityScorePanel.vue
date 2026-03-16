<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import ScoreRing from './ScoreRing.vue';
import DimensionBar from './DimensionBar.vue';

const props = defineProps({
    contentId: { type: String, required: true },
    initialScore: { type: Object, default: null },
});

const score = ref(props.initialScore);
const loading = ref(false);
const triggering = ref(false);
const error = ref(null);

const dimensions = computed(() => {
    if (!score.value) return [];
    const d = score.value.dimensions ?? {};
    return [
        { key: 'readability', label: 'Readability', score: d.readability ?? null },
        { key: 'seo', label: 'SEO', score: d.seo ?? null },
        { key: 'brand', label: 'Brand', score: d.brand ?? null },
        { key: 'factual', label: 'Factual', score: d.factual ?? null },
        { key: 'engagement', label: 'Engagement', score: d.engagement ?? null },
    ];
});

const overallScore = computed(() => score.value?.overall_score ?? null);

const scoreLabel = computed(() => {
    const s = overallScore.value;
    if (s === null) return 'Not scored';
    if (s >= 90) return 'Excellent';
    if (s >= 75) return 'Good';
    if (s >= 60) return 'Fair';
    if (s >= 40) return 'Poor';
    return 'Critical';
});

async function loadLatestScore() {
    loading.value = true;
    error.value = null;
    try {
        const res = await axios.get('/api/v1/quality/scores', {
            params: { content_id: props.contentId, per_page: 1 },
        });
        score.value = res.data.data?.[0] ?? null;
    } catch (e) {
        error.value = 'Failed to load quality score.';
    } finally {
        loading.value = false;
    }
}

async function triggerScore() {
    triggering.value = true;
    error.value = null;
    try {
        await axios.post('/api/v1/quality/score', { content_id: props.contentId });
        // Poll for updated score after a short delay
        setTimeout(() => loadLatestScore(), 3000);
    } catch (e) {
        error.value = 'Failed to trigger scoring.';
    } finally {
        triggering.value = false;
    }
}

watch(() => props.contentId, () => loadLatestScore(), { immediate: !props.initialScore });
</script>

<template>
    <div class="rounded-lg border border-gray-700 bg-gray-800 p-4">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-200">Quality Score</h3>
            <button
                class="rounded px-2 py-1 text-xs text-indigo-400 hover:bg-indigo-900/30 disabled:opacity-50"
                :disabled="triggering || loading"
                @click="triggerScore"
            >
                {{ triggering ? 'Scoring…' : 'Re-score' }}
            </button>
        </div>

        <div v-if="loading" class="flex justify-center py-6">
            <span class="text-sm text-gray-400">Loading…</span>
        </div>

        <div v-else-if="error" class="rounded bg-red-900/30 p-2 text-xs text-red-400">
            {{ error }}
        </div>

        <template v-else>
            <!-- Overall ring -->
            <div class="mb-4 flex justify-center">
                <ScoreRing :score="overallScore" :size="96" :stroke-width="10" :label="scoreLabel" />
            </div>

            <!-- No score yet -->
            <div v-if="score === null" class="text-center text-xs text-gray-500">
                No score yet.
                <button class="ml-1 text-indigo-400 hover:underline" @click="triggerScore">
                    Score now
                </button>
            </div>

            <!-- Dimension bars -->
            <template v-else>
                <div class="flex flex-col gap-3">
                    <DimensionBar
                        v-for="dim in dimensions"
                        :key="dim.key"
                        :label="dim.label"
                        :score="dim.score"
                    />
                </div>

                <p v-if="score.scored_at" class="mt-3 text-right text-xs text-gray-500">
                    Scored {{ new Date(score.scored_at).toLocaleString() }}
                </p>
            </template>
        </template>
    </div>
</template>
