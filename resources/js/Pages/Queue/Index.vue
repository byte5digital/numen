<script setup>
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    stats: Object,
    jobs: Array,
    failedJobs: Array,
    pipelineRuns: Array,
});

const tab = ref('overview');

const retryJob = (id) => router.post(`/admin/queue/retry/${id}`);
const flushFailed = () => {
    if (confirm('Clear all failed jobs?')) {
        router.post('/admin/queue/flush');
    }
};
const refresh = () => router.reload();

const statusColor = (status) => ({
    processing: 'text-amber-400 bg-amber-400/10',
    pending: 'text-blue-400 bg-blue-400/10',
    running: 'text-amber-400 bg-amber-400/10',
    completed: 'text-emerald-400 bg-emerald-400/10',
    failed: 'text-red-400 bg-red-400/10',
    paused_for_review: 'text-purple-400 bg-purple-400/10',
}[status] || 'text-gray-400 bg-gray-400/10');
</script>

<template>
    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">Queue Monitor</h1>
                <p class="text-sm text-gray-500 mt-1">Pipeline job processing & worker status</p>
            </div>
            <button @click="refresh" class="px-4 py-2 bg-gray-800 text-gray-300 rounded-lg hover:bg-gray-700 transition text-sm">
                ↻ Refresh
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500">Worker Status</p>
                    <span class="w-3 h-3 rounded-full" :class="stats.worker_running ? 'bg-emerald-400 animate-pulse' : 'bg-red-400'"></span>
                </div>
                <p class="text-2xl font-bold mt-2" :class="stats.worker_running ? 'text-emerald-400' : 'text-red-400'">
                    {{ stats.worker_running ? 'Running' : 'Stopped' }}
                </p>
            </div>
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                <p class="text-sm text-gray-500">Pending Jobs</p>
                <p class="text-2xl font-bold text-indigo-500 mt-2">{{ stats.pending }}</p>
            </div>
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                <p class="text-sm text-gray-500">Failed Jobs</p>
                <p class="text-2xl font-bold mt-2" :class="stats.failed > 0 ? 'text-red-400' : 'text-gray-400'">{{ stats.failed }}</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 bg-gray-900 rounded-lg p-1 w-fit">
            <button v-for="t in ['overview', 'pending', 'failed']" :key="t"
                @click="tab = t"
                class="px-4 py-1.5 rounded-md text-sm font-medium transition"
                :class="tab === t ? 'bg-gray-700 text-white' : 'text-gray-400 hover:text-white'">
                {{ t.charAt(0).toUpperCase() + t.slice(1) }}
                <span v-if="t === 'pending' && stats.pending" class="ml-1 text-xs text-indigo-500">({{ stats.pending }})</span>
                <span v-if="t === 'failed' && stats.failed" class="ml-1 text-xs text-red-400">({{ stats.failed }})</span>
            </button>
        </div>

        <!-- Overview: Pipeline Runs -->
        <div v-if="tab === 'overview'" class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-800">
                <h3 class="font-semibold text-white">Pipeline Runs</h3>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500 text-left">
                        <th class="px-5 py-3 font-medium">Brief</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Stage</th>
                        <th class="px-5 py-3 font-medium">Started</th>
                        <th class="px-5 py-3 font-medium">Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="run in pipelineRuns" :key="run.id" class="border-t border-gray-800/50 hover:bg-gray-800/30">
                        <td class="px-5 py-3 text-gray-300">{{ run.brief_title }}</td>
                        <td class="px-5 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusColor(run.status)">
                                {{ run.status }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-gray-400">{{ run.current_stage || '—' }}</td>
                        <td class="px-5 py-3 text-gray-500">{{ run.started_at }}</td>
                        <td class="px-5 py-3 text-gray-500">{{ run.updated_at }}</td>
                    </tr>
                    <tr v-if="!pipelineRuns?.length">
                        <td colspan="5" class="px-5 py-8 text-center text-gray-600">No pipeline runs yet</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pending Jobs -->
        <div v-if="tab === 'pending'" class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-800">
                <h3 class="font-semibold text-white">Pending Jobs</h3>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500 text-left">
                        <th class="px-5 py-3 font-medium">Job</th>
                        <th class="px-5 py-3 font-medium">Queue</th>
                        <th class="px-5 py-3 font-medium">Attempts</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Created</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="job in jobs" :key="job.id" class="border-t border-gray-800/50 hover:bg-gray-800/30">
                        <td class="px-5 py-3 text-gray-300 font-mono text-xs">{{ job.payload.class }}</td>
                        <td class="px-5 py-3 text-gray-400">{{ job.queue }}</td>
                        <td class="px-5 py-3 text-gray-400">{{ job.attempts }}</td>
                        <td class="px-5 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="statusColor(job.status)">
                                {{ job.status }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-gray-500">{{ job.created_at }}</td>
                    </tr>
                    <tr v-if="!jobs?.length">
                        <td colspan="5" class="px-5 py-8 text-center text-gray-600">No pending jobs — queue is clear 🎉</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Failed Jobs -->
        <div v-if="tab === 'failed'" class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-800 flex items-center justify-between">
                <h3 class="font-semibold text-white">Failed Jobs</h3>
                <button v-if="failedJobs?.length" @click="flushFailed" class="text-xs text-red-400 hover:text-red-300 transition">
                    Clear All
                </button>
            </div>
            <div v-for="job in failedJobs" :key="job.id" class="border-t border-gray-800/50 p-5 hover:bg-gray-800/30">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-gray-300 font-mono text-sm">{{ job.payload.class }}</span>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-gray-500">{{ job.failed_at }}</span>
                        <button @click="retryJob(job.id)" class="text-xs text-indigo-500 hover:underline">Retry</button>
                    </div>
                </div>
                <pre class="text-xs text-red-400/70 bg-gray-950 rounded p-3 overflow-x-auto max-h-32">{{ job.exception }}</pre>
            </div>
            <div v-if="!failedJobs?.length" class="px-5 py-8 text-center text-gray-600">No failed jobs ✅</div>
        </div>

        <!-- Worker instructions if not running -->
        <div v-if="!stats.worker_running" class="bg-amber-500/5 border border-amber-500/20 rounded-xl p-5">
            <h4 class="text-amber-400 font-semibold mb-2">⚠ Queue Worker Not Running</h4>
            <p class="text-sm text-gray-400 mb-3">Jobs are queued but won't process until a worker is started. The worker runs pipeline stages (AI generation, SEO, review) in the background.</p>
            <code class="block bg-gray-950 text-gray-300 rounded p-3 text-sm font-mono">php artisan queue:work --queue=ai-pipeline,default --tries=5 --timeout=240</code>
        </div>
    </div>
</template>
