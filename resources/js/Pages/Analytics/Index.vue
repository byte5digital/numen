<script setup>
import { computed } from 'vue';

const props = defineProps({
    dailyCosts: Array,
    modelBreakdown: Array,
    purposeBreakdown: Array,
    totalCost: Number,
    totalCalls: Number,
    totalTokens: Object,
    imageTotals: Object,
});

const topModels = computed(() =>
    (props.modelBreakdown || []).sort((a, b) => b.total_cost - a.total_cost)
);

const purposes = computed(() =>
    (props.purposeBreakdown || []).sort((a, b) => b.total_cost - a.total_cost)
);

const purposeLabels = {
    content_generation: { label: '✍️ Content Generation', color: 'text-indigo-400' },
    content_refresh:    { label: '✏️ Content Update', color: 'text-purple-400' },
    seo_optimization:   { label: '🔍 SEO Optimization', color: 'text-blue-400' },
    quality_review:     { label: '📋 Quality Review', color: 'text-amber-400' },
    image_generation:   { label: '🎨 Image Generation', color: 'text-pink-400' },
    image_prompt:       { label: '💬 Image Prompt', color: 'text-pink-300' },
};
</script>

<template>
    <div>
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-white">AI Cost Analytics</h1>
            <p class="text-gray-500 mt-1">Token usage, model costs, and budget tracking</p>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                <p class="text-xs text-gray-500">Total Spend</p>
                <p class="text-2xl font-bold text-white mt-1">${{ (totalCost || 0).toFixed(2) }}</p>
            </div>
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                <p class="text-xs text-gray-500">API Calls</p>
                <p class="text-2xl font-bold text-white mt-1">{{ totalCalls || 0 }}</p>
            </div>
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                <p class="text-xs text-gray-500">Input Tokens</p>
                <p class="text-2xl font-bold text-white mt-1">{{ ((totalTokens?.input || 0) / 1000).toFixed(1) }}k</p>
            </div>
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                <p class="text-xs text-gray-500">Output Tokens</p>
                <p class="text-2xl font-bold text-white mt-1">{{ ((totalTokens?.output || 0) / 1000).toFixed(1) }}k</p>
            </div>
            <div class="bg-pink-900/20 rounded-xl border border-pink-800/30 p-5">
                <p class="text-xs text-pink-400">🎨 Images Generated</p>
                <p class="text-2xl font-bold text-pink-300 mt-1">{{ imageTotals?.count || 0 }}</p>
                <p class="text-xs text-pink-400/60 mt-1">${{ (imageTotals?.cost || 0).toFixed(2) }} spent</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Cost by Model -->
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Cost by Model</h2>
                <div v-if="topModels.length" class="space-y-3">
                    <div v-for="model in topModels" :key="model.model"
                         class="flex items-center justify-between py-2 border-b border-gray-800 last:border-0">
                        <div>
                            <p class="text-sm font-mono"
                               :class="model.model === 'dall-e-3' ? 'text-pink-400' : 'text-indigo-400'">
                                {{ model.model }}
                                <span v-if="model.model === 'dall-e-3'" class="text-xs ml-1">🎨</span>
                            </p>
                            <p class="text-xs text-gray-500">{{ model.calls }} {{ model.calls === 1 ? 'call' : 'calls' }}</p>
                        </div>
                        <span class="text-sm text-white font-medium">${{ model.total_cost.toFixed(4) }}</span>
                    </div>
                </div>
                <p v-else class="text-gray-600 text-sm">No API calls yet.</p>
            </div>

            <!-- Cost by Purpose -->
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Cost by Purpose</h2>
                <div v-if="purposes.length" class="space-y-3">
                    <div v-for="item in purposes" :key="item.purpose"
                         class="flex items-center justify-between py-2 border-b border-gray-800 last:border-0">
                        <div>
                            <p class="text-sm"
                               :class="purposeLabels[item.purpose]?.color || 'text-gray-400'">
                                {{ purposeLabels[item.purpose]?.label || item.purpose }}
                            </p>
                            <p class="text-xs text-gray-500">{{ item.calls }} {{ item.calls === 1 ? 'call' : 'calls' }}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-sm text-white font-medium">${{ item.total_cost.toFixed(4) }}</span>
                            <div class="w-24 h-1.5 bg-gray-800 rounded-full overflow-hidden mt-1">
                                <div class="h-full rounded-full"
                                     :class="item.purpose === 'image_generation' ? 'bg-pink-500' : 'bg-indigo-500'"
                                     :style="{ width: `${Math.min((item.total_cost / (totalCost || 1)) * 100, 100)}%` }" />
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-gray-600 text-sm">No data yet.</p>
            </div>
        </div>

        <!-- Daily Spend -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
            <h2 class="text-lg font-semibold text-white mb-4">Daily Spend</h2>
            <div v-if="dailyCosts?.length" class="space-y-2">
                <div v-for="day in dailyCosts" :key="day.date" class="flex items-center gap-3">
                    <span class="text-xs text-gray-500 w-20">{{ day.date }}</span>
                    <div class="flex-1 h-4 bg-gray-800 rounded-full overflow-hidden">
                        <div
                            class="h-full bg-indigo-500 rounded-full"
                            :style="{ width: `${Math.min((day.cost / Math.max(...dailyCosts.map(d => d.cost), 1)) * 100, 100)}%` }"
                        ></div>
                    </div>
                    <span class="text-xs text-gray-400 w-16 text-right">${{ day.cost.toFixed(2) }}</span>
                </div>
            </div>
            <p v-else class="text-gray-600 text-sm">No spending data yet.</p>
        </div>
    </div>
</template>
