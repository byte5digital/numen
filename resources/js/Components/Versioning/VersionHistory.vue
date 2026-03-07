<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    contentId: { type: [String, Number], required: true },
    versions:  { type: Array, default: () => [] },
    activeVersionId: { type: [String, Number], default: null },
});

const emit = defineEmits(['version-selected', 'versions-updated']);

// ── State ──────────────────────────────────────────────────────────────────
const loadingAction = ref(null);   // versionId or 'draft'
const editingLabelId = ref(null);
const labelInput = ref('');
const confirmModal = ref(null);    // { type: 'rollback'|'publish', version }
const error = ref(null);

// ── Helpers ─────────────────────────────────────────────────────────────────
async function csrfCookie() {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
}

function xsrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function apiFetch(url, options = {}) {
    await csrfCookie();
    const res = await fetch(url, {
        credentials: 'include',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': xsrfToken(),
            ...options.headers,
        },
        ...options,
    });
    if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        throw new Error(body.message ?? `HTTP ${res.status}`);
    }
    return res.status === 204 ? null : res.json();
}

// ── Version display helpers ──────────────────────────────────────────────────
function statusClass(v) {
    if (v.scheduled_at) return 'bg-amber-900/50 text-amber-400';
    if (v.status === 'published') return 'bg-emerald-900/50 text-emerald-400';
    if (v.status === 'draft')     return 'bg-gray-800 text-gray-400';
    return 'bg-gray-800 text-gray-400';
}

function statusLabel(v) {
    if (v.scheduled_at) return '⏰ Scheduled';
    if (v.status === 'published') return 'Published';
    return 'Draft';
}

function scoreColor(score) {
    if (!score) return 'text-gray-600';
    return score >= 80 ? 'text-emerald-400' : score >= 60 ? 'text-amber-400' : 'text-red-400';
}

function formatDate(dt) {
    if (!dt) return '';
    const d = new Date(dt);
    if (isNaN(d.getTime())) return dt; // fallback: show raw string (e.g. "2 hours ago")
    return d.toLocaleDateString('en-GB', {
        day: '2-digit', month: 'short', year: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

const sortedVersions = computed(() =>
    [...props.versions].sort((a, b) => b.version_number - a.version_number)
);

// ── Actions ─────────────────────────────────────────────────────────────────
async function createDraft() {
    if (loadingAction.value) return;
    loadingAction.value = 'draft';
    error.value = null;
    try {
        const res = await apiFetch(`/api/content/${props.contentId}/versions/draft`, { method: 'POST' });
        emit('versions-updated', res?.data ?? null);
    } catch (e) {
        error.value = e.message;
    } finally {
        loadingAction.value = null;
    }
}

async function branch(version) {
    if (loadingAction.value) return;
    loadingAction.value = version.id;
    error.value = null;
    try {
        const res = await apiFetch(`/api/content/${props.contentId}/versions/${version.id}/branch`, { method: 'POST' });
        emit('versions-updated', res?.data ?? null);
    } catch (e) {
        error.value = e.message;
    } finally {
        loadingAction.value = null;
    }
}

function confirmRollback(version) {
    confirmModal.value = { type: 'rollback', version };
}

function confirmPublish(version) {
    confirmModal.value = { type: 'publish', version };
}

async function executeConfirmed() {
    const modal = confirmModal.value;
    if (!modal) return;
    confirmModal.value = null;

    const { type, version } = modal;
    loadingAction.value = version.id;
    error.value = null;
    try {
        const res = await apiFetch(
            `/api/content/${props.contentId}/versions/${version.id}/${type}`,
            { method: 'POST' }
        );
        emit('versions-updated', res?.data ?? null);
    } catch (e) {
        error.value = e.message;
    } finally {
        loadingAction.value = null;
    }
}

function startEditLabel(version) {
    editingLabelId.value = version.id;
    labelInput.value = version.label ?? '';
}

async function saveLabel(version) {
    if (!labelInput.value.trim()) {
        editingLabelId.value = null;
        return;
    }
    loadingAction.value = version.id;
    error.value = null;
    try {
        await apiFetch(`/api/content/${props.contentId}/versions/${version.id}/label`, {
            method: 'POST',
            body: JSON.stringify({ label: labelInput.value.trim() }),
        });
        emit('versions-updated', null);
    } catch (e) {
        error.value = e.message;
    } finally {
        loadingAction.value = null;
        editingLabelId.value = null;
    }
}
</script>

<template>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-medium text-white">🕐 Version History</h3>
            <button
                @click="createDraft"
                :disabled="!!loadingAction"
                class="text-xs px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white rounded-lg transition"
            >
                {{ loadingAction === 'draft' ? 'Creating…' : '+ Draft' }}
            </button>
        </div>

        <!-- Error -->
        <div v-if="error" class="mb-3 px-3 py-2 bg-red-900/30 border border-red-500/30 rounded-lg text-red-400 text-xs">
            {{ error }}
            <button @click="error = null" class="ml-2 opacity-60 hover:opacity-100">✕</button>
        </div>

        <!-- Empty state -->
        <div v-if="!sortedVersions.length" class="text-xs text-gray-600 text-center py-4">
            No versions yet.
        </div>

        <!-- Version list -->
        <div class="space-y-2">
            <div
                v-for="v in sortedVersions"
                :key="v.id"
                class="rounded-lg border transition cursor-pointer"
                :class="v.id === activeVersionId
                    ? 'border-indigo-500/50 bg-indigo-900/20'
                    : 'border-gray-800 hover:border-gray-700 bg-gray-950/50'"
                @click="emit('version-selected', v)"
            >
                <!-- Version row -->
                <div class="p-3">
                    <div class="flex items-start justify-between gap-2">
                        <!-- Left: version info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-xs font-mono text-gray-400">v{{ v.version_number }}</span>
                                <span class="text-xs px-1.5 py-0.5 rounded-full" :class="statusClass(v)">
                                    {{ statusLabel(v) }}
                                </span>
                                <span v-if="v.id === activeVersionId"
                                      class="text-xs px-1.5 py-0.5 bg-indigo-500/20 text-indigo-400 rounded-full">
                                    viewing
                                </span>
                            </div>

                            <!-- Label (editable) -->
                            <div class="mt-1.5" @click.stop>
                                <div v-if="editingLabelId === v.id" class="flex items-center gap-1">
                                    <input
                                        v-model="labelInput"
                                        @keydown.enter="saveLabel(v)"
                                        @keydown.escape="editingLabelId = null"
                                        class="flex-1 text-xs bg-gray-800 border border-indigo-500 rounded px-2 py-0.5 text-white focus:outline-none"
                                        placeholder="Version label…"
                                        autofocus
                                    />
                                    <button @click="saveLabel(v)" class="text-xs text-indigo-400 hover:text-indigo-300">✓</button>
                                    <button @click="editingLabelId = null" class="text-xs text-gray-500 hover:text-gray-400">✕</button>
                                </div>
                                <div v-else class="flex items-center gap-1 group/label">
                                    <span v-if="v.label" class="text-xs text-gray-300 truncate">{{ v.label }}</span>
                                    <span v-else class="text-xs text-gray-600 italic">Unlabelled</span>
                                    <button
                                        @click="startEditLabel(v)"
                                        class="opacity-0 group-hover/label:opacity-60 hover:!opacity-100 text-gray-500 hover:text-indigo-400 transition text-xs"
                                        title="Edit label"
                                    >✏️</button>
                                </div>
                            </div>

                            <!-- Scheduled date -->
                            <div v-if="v.scheduled_at" class="mt-1 text-xs text-amber-400">
                                📅 {{ formatDate(v.scheduled_at) }}
                            </div>

                            <!-- Meta row -->
                            <div class="flex items-center gap-2 mt-1.5">
                                <span :class="v.author_type === 'ai_agent' ? 'text-indigo-400' : 'text-emerald-400'" class="text-xs" :title="v.author_type">
                                    {{ v.author_type === 'ai_agent' ? '🤖' : '👤' }}
                                </span>
                                <span v-if="v.quality_score" class="text-xs font-mono" :class="scoreColor(v.quality_score)">
                                    Q{{ v.quality_score }}
                                </span>
                                <span class="text-xs text-gray-600">{{ formatDate(v.created_at) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-1 mt-2.5 flex-wrap" @click.stop>
                        <!-- Branch (all versions) -->
                        <button
                            @click="branch(v)"
                            :disabled="!!loadingAction"
                            class="text-xs px-2 py-1 border border-gray-700 text-gray-400 rounded hover:border-gray-500 hover:text-white disabled:opacity-40 transition"
                        >
                            {{ loadingAction === v.id ? '⏳' : '🌿 Branch' }}
                        </button>

                        <!-- Rollback (non-current historical versions) -->
                        <button
                            v-if="v.status !== 'draft'"
                            @click="confirmRollback(v)"
                            :disabled="!!loadingAction"
                            class="text-xs px-2 py-1 border border-gray-700 text-gray-400 rounded hover:border-amber-500/50 hover:text-amber-400 disabled:opacity-40 transition"
                        >
                            ↩ Rollback
                        </button>

                        <!-- Publish (draft only) -->
                        <button
                            v-if="v.status === 'draft' && !v.scheduled_at"
                            @click="confirmPublish(v)"
                            :disabled="!!loadingAction"
                            class="text-xs px-2 py-1 border border-emerald-700/50 text-emerald-400 rounded hover:bg-emerald-900/30 disabled:opacity-40 transition"
                        >
                            ✓ Publish
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <Teleport to="body">
            <div v-if="confirmModal"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
                 @click.self="confirmModal = null">
                <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 w-80 shadow-2xl">
                    <h4 class="text-sm font-semibold text-white mb-2">
                        {{ confirmModal.type === 'rollback' ? '↩ Confirm Rollback' : '✓ Confirm Publish' }}
                    </h4>
                    <p class="text-xs text-gray-400 mb-5">
                        <template v-if="confirmModal.type === 'rollback'">
                            This will create a new draft based on <strong class="text-white">v{{ confirmModal.version.version_number }}</strong>.
                            The current published version stays live until you publish the new draft.
                        </template>
                        <template v-else>
                            Publish <strong class="text-white">v{{ confirmModal.version.version_number }}</strong>? This will replace the current live version.
                        </template>
                    </p>
                    <div class="flex items-center gap-2 justify-end">
                        <button @click="confirmModal = null"
                                class="px-3 py-1.5 text-xs text-gray-400 hover:text-white transition">
                            Cancel
                        </button>
                        <button
                            @click="executeConfirmed"
                            class="px-4 py-1.5 text-xs rounded-lg text-white transition font-medium"
                            :class="confirmModal.type === 'rollback'
                                ? 'bg-amber-600 hover:bg-amber-500'
                                : 'bg-emerald-600 hover:bg-emerald-500'"
                        >
                            {{ confirmModal.type === 'rollback' ? 'Rollback' : 'Publish' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
