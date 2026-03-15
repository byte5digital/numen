<script setup>
import ContentGapsWidget from '../../Components/Graph/ContentGapsWidget.vue';
import { computed } from 'vue';

const props = defineProps({
    stats:         { type: Object,  default: () => ({}) },
    recentContent: { type: Array,   default: () => [] },
    recentRuns:    { type: Array,   default: () => [] },
    costToday:     { type: Number,  default: 0 },
    providers:     { type: Array,   default: () => [] },
    fallbackChain: { type: Array,   default: () => [] },
    defaultSpaceId: { type: String, default: '' },
});

const statCards = computed(() => [
    { label: 'Published', value: props.stats?.published ?? 0, icon: '📝', color: 'emerald' },
    { label: 'In Pipeline', value: props.stats?.in_pipeline ?? 0, icon: '⚡', color: 'indigo' },
    { label: 'Pending Review', value: props.stats?.pending_review ?? 0, icon: '👁️', color: 'amber' },
    { label: 'Cost Today', value: `$${(props.costToday ?? 0).toFixed(2)}`, icon: '💰', color: 'rose' },
]);
</script>

<template>
    <div>
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-white">Dashboard</h1>
            <p class="text-gray-500 mt-1">AI-First Content Management</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div
                v-for="stat in statCards"
                :key="stat.label"
                class="bg-gray-900 rounded-xl border border-gray-800 p-5"
            >
                <div class="flex items-center justify-between">
                    <span class="text-2xl">{{ stat.icon }}</span>
                    <span class="text-2xl font-bold text-white">{{ stat.value }}</span>
                </div>
                <p class="mt-2 text-sm text-gray-500">{{ stat.label }}</p>
            </div>
        </div>

        <!-- AI Provider Status -->
        <div class="mb-6 bg-gray-900 rounded-xl border border-gray-800 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-white">AI Providers</h2>
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <span>Fallback chain:</span>
                    <div class="flex items-center gap-1">
                        <span v-for="(p, i) in fallbackChain" :key="p"
                              class="flex items-center gap-1">
                            <span class="px-2 py-0.5 rounded bg-gray-800 text-gray-300 font-mono">{{ p }}</span>
                            <span v-if="i < fallbackChain.length - 1" class="text-gray-600">→</span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div v-for="provider in providers" :key="provider.name"
                     class="flex items-start gap-3 p-3 rounded-lg border"
                     :class="provider.is_default
                         ? 'border-indigo-500/30 bg-indigo-500/5'
                         : 'border-gray-800 bg-gray-950'">
                    <!-- Status dot -->
                    <div class="mt-0.5 h-2.5 w-2.5 rounded-full shrink-0"
                         :class="!provider.key_set
                             ? 'bg-gray-700'
                             : provider.available
                                 ? 'bg-emerald-400 shadow-[0_0_6px_#34d399]'
                                 : 'bg-red-500'" />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-white">{{ provider.label }}</span>
                            <span v-if="provider.is_default"
                                  class="text-xs px-1.5 py-0.5 bg-indigo-500/20 text-indigo-300 rounded">
                                default
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5 font-mono truncate">{{ provider.default_model }}</p>
                        <p class="text-xs mt-1"
                           :class="!provider.key_set
                               ? 'text-gray-600'
                               : provider.available ? 'text-emerald-400' : 'text-amber-400'">
                            {{ !provider.key_set
                                ? 'No API key configured'
                                : provider.available ? 'Available' : 'Rate limited' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Content -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Recent Content</h2>
                <div v-if="recentContent?.length" class="space-y-3">
                    <div
                        v-for="content in recentContent"
                        :key="content.id"
                        class="flex items-center justify-between py-2 border-b border-gray-800 last:border-0"
                    >
                        <div>
                            <p class="text-sm font-medium text-gray-200">{{ content.title }}</p>
                            <p class="text-xs text-gray-500">{{ content.type }} · {{ content.locale }}</p>
                        </div>
                        <span
                            class="px-2 py-1 text-xs rounded-full"
                            :class="{
                                'bg-emerald-900/50 text-emerald-400': content.status === 'published',
                                'bg-indigo-900/50 text-indigo-400': content.status === 'in_pipeline',
                                'bg-amber-900/50 text-amber-400': content.status === 'review',
                                'bg-gray-800 text-gray-400': content.status === 'draft',
                            }"
                        >
                            {{ content.status }}
                        </span>
                    </div>
                </div>
                <p v-else class="text-gray-600 text-sm">No content yet. Create a brief to get started.</p>
            </div>

            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Pipeline Activity</h2>
                <div v-if="recentRuns?.length" class="space-y-3">
                    <div
                        v-for="run in recentRuns"
                        :key="run.id"
                        class="flex items-center justify-between py-2 border-b border-gray-800 last:border-0"
                    >
                        <div>
                            <p class="text-sm font-medium text-gray-200">{{ run.brief_title }}</p>
                            <p class="text-xs text-gray-500">Stage: {{ run.current_stage }}</p>
                        </div>
                        <span
                            class="px-2 py-1 text-xs rounded-full"
                            :class="{
                                'bg-emerald-900/50 text-emerald-400': run.status === 'completed',
                                'bg-indigo-900/50 text-indigo-400': run.status === 'running',
                                'bg-amber-900/50 text-amber-400': run.status === 'paused_for_review',
                                'bg-red-900/50 text-red-400': run.status === 'failed',
                            }"
                        >
                            {{ run.status }}
                        </span>
                    </div>
                </div>
                <p v-else class="text-gray-600 text-sm">No pipeline runs yet.</p>
            </div>
        </div>

        <!-- Content Gap Analysis -->
        <div v-if="defaultSpaceId" class="mt-6">
            <ContentGapsWidget :space-id="defaultSpaceId" />
        </div>
    </div>
</template>
