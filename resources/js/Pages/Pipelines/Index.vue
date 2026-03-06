<script setup>
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';

defineProps({ pipelines: Array, runs: Object });

let refreshInterval = null;

onMounted(() => {
    refreshInterval = setInterval(() => {
        router.reload({ only: ['runs'], preserveScroll: true });
    }, 5000);
});

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval);
});

const approve = (id) => {
    if (confirm('Approve and publish this content?')) {
        router.post(`/admin/pipeline-runs/${id}/approve`);
    }
};

const reject = (id) => {
    if (confirm('Reject this pipeline run?')) {
        router.post(`/admin/pipeline-runs/${id}/reject`);
    }
};

const statusClass = (status) => ({
    completed: 'bg-emerald-900/50 text-emerald-400',
    running: 'bg-indigo-900/50 text-indigo-400',
    paused_for_review: 'bg-amber-900/50 text-amber-400',
    failed: 'bg-red-900/50 text-red-400',
    rejected: 'bg-red-900/30 text-red-300',
    pending: 'bg-gray-800 text-gray-400',
}[status] || 'bg-gray-800 text-gray-400');

const stageColors = {
    ai_generate:   { bg: 'bg-indigo-900/20',  border: 'border-l-indigo-500' },
    ai_illustrate: { bg: 'bg-pink-900/20',    border: 'border-l-pink-500' },
    ai_transform:  { bg: 'bg-purple-900/20',  border: 'border-l-purple-500' },
    ai_review:     { bg: 'bg-amber-900/20',   border: 'border-l-amber-500' },
    auto_publish:  { bg: 'bg-emerald-900/20', border: 'border-l-emerald-500' },
    human_gate:    { bg: 'bg-gray-800/50',    border: 'border-l-gray-500' },
};

const rowClass = (run) => {
    if (run.status === 'completed') return 'bg-emerald-900/10 border-l-4 border-l-emerald-500';
    if (run.status === 'failed' || run.status === 'rejected') return 'bg-red-900/10 border-l-4 border-l-red-500';
    if (run.status === 'paused_for_review') return 'bg-amber-900/10 border-l-4 border-l-amber-500';
    const colors = stageColors[run.current_stage_type];
    if (colors) return `${colors.bg} border-l-4 ${colors.border}`;
    return '';
};
</script>

<template>
    <div class="p-6">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-white">Pipelines</h1>
            <p class="text-gray-500 mt-1">Content processing pipelines and their runs
                <span class="inline-flex items-center gap-1.5 ml-2 text-xs text-gray-600">
                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                    Auto-refreshing
                </span>
            </p>
        </div>

        <!-- Pipeline Definitions -->
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-white mb-4">Pipeline Templates</h2>
            <div v-for="pipeline in pipelines" :key="pipeline.id" class="bg-gray-900 rounded-xl border border-gray-800 p-6 mb-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-white">{{ pipeline.name }}</h3>
                    <span class="px-2 py-1 text-xs rounded-full"
                        :class="pipeline.is_active ? 'bg-emerald-900/50 text-emerald-400' : 'bg-gray-800 text-gray-500'">
                        {{ pipeline.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div class="flex items-center gap-2 overflow-x-auto pb-2">
                    <template v-for="(stage, i) in pipeline.stages" :key="stage.name">
                        <div class="flex-shrink-0 px-4 py-2 rounded-lg border text-sm"
                            :class="{
                                'bg-indigo-900/30 border-indigo-700 text-indigo-300': stage.type === 'ai_generate',
                                'bg-pink-900/30 border-pink-700 text-pink-300': stage.type === 'ai_illustrate',
                                'bg-purple-900/30 border-purple-700 text-purple-300': stage.type === 'ai_transform',
                                'bg-amber-900/30 border-amber-700 text-amber-300': stage.type === 'ai_review',
                                'bg-emerald-900/30 border-emerald-700 text-emerald-300': stage.type === 'auto_publish',
                                'bg-gray-800 border-gray-700 text-gray-400': stage.type === 'human_gate',
                            }">
                            <p class="font-medium">{{ stage.name }}</p>
                            <p class="text-xs opacity-60">{{ stage.agent_role || stage.type }}</p>
                        </div>
                        <svg v-if="i < pipeline.stages.length - 1" class="w-6 h-6 text-gray-700 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </template>
                </div>
            </div>
        </div>

        <!-- Recent Runs -->
        <div>
            <h2 class="text-lg font-semibold text-white mb-4">Recent Pipeline Runs</h2>
            <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
                <table class="w-full">
                    <thead class="border-b border-gray-800">
                        <tr>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Brief</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Stage</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Score</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Updated</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <tr v-for="run in runs?.data" :key="run.id"
                            class="transition-colors duration-300"
                            :class="rowClass(run)">
                            <td class="px-6 py-4">
                                <p class="text-sm text-gray-300">{{ run.brief_title }}</p>
                                <p class="text-xs text-gray-600 font-mono mt-0.5">{{ run.id?.slice(0, 16) }}…</p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full border font-medium"
                                    :class="{
                                        'bg-indigo-900/30 border-indigo-700 text-indigo-300': run.current_stage_type === 'ai_generate',
                                        'bg-pink-900/30 border-pink-700 text-pink-300': run.current_stage_type === 'ai_illustrate',
                                        'bg-purple-900/30 border-purple-700 text-purple-300': run.current_stage_type === 'ai_transform',
                                        'bg-amber-900/30 border-amber-700 text-amber-300': run.current_stage_type === 'ai_review',
                                        'bg-emerald-900/30 border-emerald-700 text-emerald-300': run.current_stage_type === 'auto_publish',
                                        'bg-gray-800 border-gray-700 text-gray-400': !run.current_stage_type,
                                    }">
                                    {{ run.current_stage || 'done' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs rounded-full" :class="statusClass(run.status)">
                                    {{ run.status }}
                                    <span v-if="run.status === 'running'" class="inline-block w-1.5 h-1.5 bg-current rounded-full animate-pulse ml-1"></span>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="run.quality_score !== null" class="text-sm font-mono"
                                    :class="run.quality_score >= 80 ? 'text-emerald-400' : run.quality_score >= 60 ? 'text-amber-400' : 'text-red-400'">
                                    {{ run.quality_score }}
                                </span>
                                <span v-else class="text-gray-600">—</span>
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-500">{{ run.updated_at }}</td>
                            <td class="px-6 py-4">
                                <div v-if="run.status === 'paused_for_review'" class="flex items-center gap-2">
                                    <button @click="approve(run.id)"
                                        class="px-3 py-1 text-xs bg-emerald-600 text-white rounded-lg hover:bg-emerald-500 transition font-medium">
                                        ✓ Approve
                                    </button>
                                    <button @click="reject(run.id)"
                                        class="px-3 py-1 text-xs bg-red-600/20 text-red-400 rounded-lg hover:bg-red-600/30 transition font-medium">
                                        ✗ Reject
                                    </button>
                                </div>
                                <Link v-else-if="run.content_slug" :href="`/blog/${run.content_slug}`"
                                    class="text-xs text-indigo-500 hover:underline">
                                    View →
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!runs?.data?.length" class="px-6 py-12 text-center text-gray-600 text-sm">
                    No pipeline runs yet.
                </div>
            </div>
        </div>
    </div>
</template>
