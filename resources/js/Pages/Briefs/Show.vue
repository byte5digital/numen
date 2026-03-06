<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({ brief: Object });

const run = computed(() => props.brief?.pipeline_run);
const content = computed(() => run.value?.content);
const logs = computed(() => run.value?.generation_logs ?? []);
const pipeline = computed(() => run.value?.pipeline);
const targetContent = computed(() => props.brief?.target_content);
const isUpdate = computed(() => !!props.brief?.content_id);

const totalCost = computed(() =>
    logs.value.reduce((sum, l) => sum + parseFloat(l.cost_usd ?? 0), 0).toFixed(4)
);

const totalTokens = computed(() =>
    logs.value.reduce((sum, l) => sum + (l.input_tokens ?? 0) + (l.output_tokens ?? 0), 0)
);

// Auto-refresh every 5s when pipeline is running
let refreshInterval = null;
const isRunning = computed(() => ['running', 'processing', 'pending'].includes(run.value?.status) || props.brief?.status === 'processing');

onMounted(() => {
    refreshInterval = setInterval(() => {
        if (isRunning.value) {
            router.reload({ preserveScroll: true });
        }
    }, 5000);
});

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval);
});

const statusClass = {
    pending:           'bg-gray-800 text-gray-400',
    processing:        'bg-indigo-900/50 text-indigo-400',
    running:           'bg-indigo-900/50 text-indigo-400',
    completed:         'bg-emerald-900/50 text-emerald-400',
    failed:            'bg-red-900/50 text-red-400',
    cancelled:         'bg-gray-800 text-gray-500',
    in_review:         'bg-amber-900/50 text-amber-400',
    paused_for_review: 'bg-amber-900/50 text-amber-400',
};

const priorityClass = {
    urgent: 'bg-red-900/50 text-red-400',
    high:   'bg-amber-900/50 text-amber-400',
    normal: 'bg-gray-800 text-gray-400',
    low:    'bg-gray-800/50 text-gray-500',
};

const stageTypeColors = {
    ai_generate:   'bg-indigo-900/30 border-indigo-700 text-indigo-300',
    ai_illustrate: 'bg-pink-900/30 border-pink-700 text-pink-300',
    ai_transform:  'bg-purple-900/30 border-purple-700 text-purple-300',
    ai_review:     'bg-amber-900/30 border-amber-700 text-amber-300',
    auto_publish:  'bg-emerald-900/30 border-emerald-700 text-emerald-300',
    human_gate:    'bg-gray-800 border-gray-700 text-gray-400',
};

const flash = computed(() => usePage().props.flash ?? {});
const reprocessing = ref(false);

function reprocess() {
    if (!confirm('Restart the pipeline for this brief? The existing run will be deleted.')) return;
    reprocessing.value = true;
    router.post(`/admin/briefs/${props.brief.id}/reprocess`, {}, {
        onFinish: () => { reprocessing.value = false; },
    });
}

function fmt(val) {
    if (!val) return '—';
    return new Date(val).toLocaleString();
}

function stageStatus(stageName) {
    if (!run.value) return 'pending';
    const results = run.value.stage_results ?? {};
    if (results[stageName]) return 'completed';
    if (run.value.current_stage === stageName) {
        if (run.value.status === 'paused_for_review') return 'paused';
        if (run.value.status === 'failed') return 'failed';
        return 'running';
    }
    return 'pending';
}
</script>

<template>
    <div class="space-y-6">

        <!-- Flash -->
        <div v-if="flash.success"
             class="px-4 py-3 bg-emerald-500/10 border border-emerald-500/20 rounded-lg text-emerald-400 text-sm">
            {{ flash.success }}
        </div>

        <!-- Header -->
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                    <Link href="/admin/briefs" class="hover:text-gray-300">Content Briefs</Link>
                    <span>/</span>
                    <span class="text-gray-400">{{ brief.title }}</span>
                </div>
                <h1 class="text-xl font-bold text-white flex items-center gap-3">
                    {{ brief.title }}
                    <span v-if="isUpdate"
                          class="text-xs px-2.5 py-1 rounded-full bg-purple-900/50 text-purple-300 font-medium">
                        ✏️ Update Brief
                    </span>
                    <span v-else
                          class="text-xs px-2.5 py-1 rounded-full bg-indigo-900/50 text-indigo-300 font-medium">
                        ✨ New Content
                    </span>
                </h1>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="text-xs px-2.5 py-1 rounded-full font-medium"
                      :class="priorityClass[brief.priority] ?? 'bg-gray-800 text-gray-400'">
                    {{ brief.priority }}
                </span>
                <span class="text-xs px-2.5 py-1 rounded-full font-medium"
                      :class="statusClass[brief.status] ?? 'bg-gray-800 text-gray-400'">
                    {{ brief.status }}
                    <span v-if="isRunning" class="inline-block w-1.5 h-1.5 bg-current rounded-full animate-pulse ml-1"></span>
                </span>
                <button @click="reprocess" :disabled="reprocessing"
                        class="ml-2 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-xs font-medium rounded-lg transition">
                    {{ reprocessing ? 'Restarting…' : '↻ Reprocess' }}
                </button>
            </div>
        </div>

        <!-- Pipeline Progress Bar -->
        <div v-if="pipeline?.stages" class="bg-gray-900 rounded-xl border border-gray-800 p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-white">Pipeline Progress</h2>
                <span v-if="isRunning" class="text-xs text-gray-500 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                    Auto-refreshing
                </span>
            </div>
            <div class="flex items-center gap-2 overflow-x-auto pb-1">
                <template v-for="(stage, i) in pipeline.stages" :key="stage.name">
                    <div class="flex-shrink-0 px-4 py-2.5 rounded-lg border text-sm relative"
                        :class="{
                            ...Object.fromEntries([[stageTypeColors[stage.type] ?? 'bg-gray-800 border-gray-700 text-gray-400', stageStatus(stage.name) === 'completed']]),
                            'bg-gray-800 border-gray-700 text-gray-500 opacity-50': stageStatus(stage.name) === 'pending',
                            'ring-2 ring-indigo-500 ring-offset-1 ring-offset-gray-900': stageStatus(stage.name) === 'running',
                            'ring-2 ring-amber-500 ring-offset-1 ring-offset-gray-900': stageStatus(stage.name) === 'paused',
                            'ring-2 ring-red-500 ring-offset-1 ring-offset-gray-900': stageStatus(stage.name) === 'failed',
                            [stageTypeColors[stage.type]]: stageStatus(stage.name) === 'completed' || stageStatus(stage.name) === 'running',
                        }">
                        <p class="font-medium flex items-center gap-1.5">
                            <span v-if="stageStatus(stage.name) === 'completed'" class="text-emerald-400">✓</span>
                            <span v-else-if="stageStatus(stage.name) === 'running'" class="inline-block w-2 h-2 bg-indigo-400 rounded-full animate-pulse"></span>
                            <span v-else-if="stageStatus(stage.name) === 'paused'" class="text-amber-400">⏸</span>
                            <span v-else-if="stageStatus(stage.name) === 'failed'" class="text-red-400">✗</span>
                            {{ stage.name }}
                        </p>
                        <p class="text-xs opacity-60 mt-0.5">{{ stage.agent_role || stage.type }}</p>
                    </div>
                    <svg v-if="i < pipeline.stages.length - 1"
                         class="w-5 h-5 flex-shrink-0"
                         :class="stageStatus(pipeline.stages[i+1]?.name) !== 'pending' ? 'text-emerald-600' : 'text-gray-700'"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </template>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left: Brief details -->
            <div class="space-y-4">

                <!-- Target content (update briefs) -->
                <div v-if="isUpdate && targetContent" class="bg-purple-900/10 rounded-xl border border-purple-800/50 p-5">
                    <h2 class="text-sm font-semibold text-purple-300 mb-3">📄 Updating Existing Content</h2>
                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">Content</dt>
                            <dd>
                                <Link :href="`/admin/content/${targetContent.id}`"
                                      class="text-indigo-400 hover:text-indigo-300 text-sm">
                                    {{ targetContent.current_version?.title ?? targetContent.slug }}
                                </Link>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">Current Version</dt>
                            <dd class="text-gray-400 text-xs">v{{ targetContent.current_version?.version_number ?? '?' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                    <h2 class="text-sm font-semibold text-white mb-4">Brief Details</h2>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">Type</dt>
                            <dd class="text-gray-300 font-mono">{{ brief.content_type_slug }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">Source</dt>
                            <dd class="text-gray-300">
                                <span class="px-2 py-0.5 text-xs rounded-full"
                                      :class="brief.source === 'update_brief' ? 'bg-purple-900/30 text-purple-300' : 'bg-gray-800 text-gray-400'">
                                    {{ brief.source }}
                                </span>
                            </dd>
                        </div>
                        <div v-if="brief.description">
                            <dt class="text-xs text-gray-500 mb-0.5">{{ isUpdate ? 'Update Instructions' : 'Description' }}</dt>
                            <dd class="text-gray-300 leading-relaxed bg-gray-800/50 rounded-lg p-3 mt-1">{{ brief.description }}</dd>
                        </div>
                        <div v-if="brief.target_keywords?.length">
                            <dt class="text-xs text-gray-500 mb-0.5">Keywords</dt>
                            <dd class="flex flex-wrap gap-1 mt-1">
                                <span v-for="kw in brief.target_keywords" :key="kw"
                                      class="text-xs px-2 py-0.5 bg-gray-800 text-gray-300 rounded">
                                    {{ kw }}
                                </span>
                            </dd>
                        </div>
                        <div v-if="brief.target_locale">
                            <dt class="text-xs text-gray-500 mb-0.5">Locale</dt>
                            <dd class="text-gray-300">{{ brief.target_locale }}</dd>
                        </div>
                        <div v-if="brief.persona">
                            <dt class="text-xs text-gray-500 mb-0.5">Persona</dt>
                            <dd class="text-gray-300">{{ brief.persona.name }}
                                <span class="text-gray-500 text-xs ml-1">({{ brief.persona.role }})</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">Created</dt>
                            <dd class="text-gray-400 text-xs">{{ fmt(brief.created_at) }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Pipeline run summary -->
                <div v-if="run" class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                    <h2 class="text-sm font-semibold text-white mb-4">Pipeline Run</h2>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">Status</dt>
                            <dd>
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                      :class="statusClass[run.status] ?? 'bg-gray-800 text-gray-400'">
                                    {{ run.status }}
                                    <span v-if="run.status === 'running'" class="inline-block w-1.5 h-1.5 bg-current rounded-full animate-pulse ml-1"></span>
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">Current Stage</dt>
                            <dd class="text-gray-300 font-mono text-xs">{{ run.current_stage ?? 'done' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 mb-0.5">Started</dt>
                            <dd class="text-gray-400 text-xs">{{ fmt(run.started_at) }}</dd>
                        </div>
                        <div v-if="run.completed_at">
                            <dt class="text-xs text-gray-500 mb-0.5">Completed</dt>
                            <dd class="text-gray-400 text-xs">{{ fmt(run.completed_at) }}</dd>
                        </div>
                        <div class="pt-2 border-t border-gray-800 grid grid-cols-2 gap-3">
                            <div>
                                <dt class="text-xs text-gray-500">Total Cost</dt>
                                <dd class="text-white font-mono text-sm">${{ totalCost }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Tokens</dt>
                                <dd class="text-white font-mono text-sm">{{ totalTokens.toLocaleString() }}</dd>
                            </div>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Right: Content output + AI logs -->
            <div class="lg:col-span-2 space-y-4">

                <!-- Generated content -->
                <div v-if="content" class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold text-white">
                            {{ isUpdate ? 'Updated Content' : 'Generated Content' }}
                        </h2>
                        <div class="flex gap-2">
                            <Link :href="`/admin/content/${content.id}`"
                                  class="text-xs text-indigo-400 hover:text-indigo-300">
                                Open editor →
                            </Link>
                            <a v-if="content.status === 'published'"
                               :href="`/blog/${content.slug}`" target="_blank"
                               class="text-xs text-emerald-400 hover:text-emerald-300">
                                View live ↗
                            </a>
                        </div>
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex gap-4">
                            <div class="flex-1">
                                <dt class="text-xs text-gray-500 mb-0.5">Title</dt>
                                <dd class="text-gray-200">{{ content.current_version?.title ?? content.slug }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 mb-0.5">Status</dt>
                                <dd>
                                    <span class="text-xs px-2 py-0.5 rounded-full"
                                          :class="statusClass[content.status] ?? 'bg-gray-800 text-gray-400'">
                                        {{ content.status }}
                                    </span>
                                </dd>
                            </div>
                        </div>
                        <div v-if="content.current_version?.version_number">
                            <dt class="text-xs text-gray-500 mb-0.5">Version</dt>
                            <dd class="text-gray-300">v{{ content.current_version.version_number }}</dd>
                        </div>
                        <div v-if="content.current_version?.excerpt">
                            <dt class="text-xs text-gray-500 mb-0.5">Excerpt</dt>
                            <dd class="text-gray-400 text-sm leading-relaxed line-clamp-3">
                                {{ content.current_version.excerpt }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div v-else-if="run" class="bg-gray-900 rounded-xl border border-gray-800 p-5 text-center">
                    <div v-if="['running', 'processing'].includes(run.status)" class="py-8">
                        <div class="inline-flex items-center gap-3 text-indigo-400">
                            <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm">Pipeline is running — {{ run.current_stage }}…</span>
                        </div>
                    </div>
                    <p v-else class="text-gray-500 text-sm py-4">No content generated yet.</p>
                </div>

                <!-- AI generation log -->
                <div v-if="logs.length" class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                    <h2 class="text-sm font-semibold text-white mb-4">AI Generation Log</h2>
                    <div class="space-y-2">
                        <div v-for="log in logs" :key="log.id"
                             class="flex items-center gap-4 px-3 py-2 rounded-lg text-xs"
                             :class="log.purpose === 'image_generation' ? 'bg-pink-900/20 border border-pink-800/30' : 'bg-gray-800/50'">
                            <span class="font-mono w-28 shrink-0"
                                  :class="log.purpose === 'image_generation' ? 'text-pink-300' : 'text-gray-400'">
                                {{ log.purpose }}
                            </span>
                            <span class="w-32 shrink-0"
                                  :class="log.purpose === 'image_generation' ? 'text-pink-400' : 'text-gray-500'">
                                {{ log.model }}
                            </span>
                            <span class="text-gray-400">
                                <template v-if="log.purpose === 'image_generation'">
                                    🎨 {{ log.metadata?.size || '1792×1024' }}
                                </template>
                                <template v-else>
                                    {{ ((log.input_tokens ?? 0) + (log.output_tokens ?? 0)).toLocaleString() }} tok
                                </template>
                            </span>
                            <span class="text-emerald-400 ml-auto">${{ parseFloat(log.cost_usd ?? 0).toFixed(4) }}</span>
                            <span class="text-gray-600">{{ log.latency_ms ? (log.latency_ms / 1000).toFixed(1) + 's' : '' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Stage results detail -->
                <div v-if="run?.stage_results && Object.keys(run.stage_results).length"
                     class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                    <h2 class="text-sm font-semibold text-white mb-4">Stage Results</h2>
                    <div class="space-y-3">
                        <div v-for="(result, stageName) in run.stage_results" :key="stageName"
                             class="px-3 py-2 bg-gray-800/50 rounded-lg text-xs">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-emerald-400">✓</span>
                                <span class="text-gray-300 font-medium">{{ stageName }}</span>
                                <span v-if="result.summary" class="text-gray-500 ml-auto truncate max-w-xs">{{ result.summary }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No run yet -->
                <div v-if="!run" class="bg-gray-900 rounded-xl border border-gray-800 p-8 text-center">
                    <p class="text-gray-500 text-sm">No pipeline run started yet.</p>
                </div>

            </div>
        </div>
    </div>
</template>
