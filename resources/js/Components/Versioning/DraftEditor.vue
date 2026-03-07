<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    contentId: { type: [String, Number], required: true },
    version:   { type: Object, required: true },
});

const emit = defineEmits(['saved', 'discarded']);

// ── State ──────────────────────────────────────────────────────────────────
const title        = ref(props.version.title        ?? '');
const excerpt      = ref(props.version.excerpt      ?? '');
const body         = ref(props.version.body         ?? '');
const changeReason = ref('');
const saving       = ref(false);
const autosaving   = ref(false);
const lastSaved    = ref(null);
const error        = ref(null);
const isDirty      = ref(false);
let   autosaveInterval = null;

// Track dirty state
watch([title, excerpt, body, changeReason], () => {
    isDirty.value = true;
});

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

// ── Auto-save ────────────────────────────────────────────────────────────────
async function autosave() {
    if (!isDirty.value || saving.value) return;
    autosaving.value = true;
    try {
        await apiFetch(`/api/content/${props.contentId}/autosave`, {
            method: 'POST',
            body: JSON.stringify({
                version_id:    props.version.id,
                title:         title.value,
                excerpt:       excerpt.value,
                body:          body.value,
                change_reason: changeReason.value || null,
            }),
        });
        lastSaved.value = new Date();
        isDirty.value   = false;
    } catch {
        // Silent — don't interrupt editing for autosave failures
    } finally {
        autosaving.value = false;
    }
}

onMounted(async () => {
    // Try to restore autosave
    try {
        const res = await apiFetch(`/api/content/${props.contentId}/autosave`);
        const saved = res?.data;
        if (saved && saved.version_id === props.version.id) {
            // Restore if the user confirms
            if (confirm('An unsaved draft was found. Restore it?')) {
                title.value   = saved.title   ?? title.value;
                excerpt.value = saved.excerpt ?? excerpt.value;
                body.value    = saved.body    ?? body.value;
                lastSaved.value = new Date(saved.updated_at ?? saved.created_at);
            }
        }
    } catch {
        // No autosave or error — ignore
    }

    // Start auto-save interval (every 30 seconds)
    autosaveInterval = setInterval(autosave, 30000);
});

onUnmounted(() => {
    clearInterval(autosaveInterval);
});

// ── Actions ──────────────────────────────────────────────────────────────────
async function saveDraft() {
    saving.value = true;
    error.value  = null;
    try {
        const res = await apiFetch(
            `/api/content/${props.contentId}/versions/${props.version.id}`,
            {
                method: 'PATCH',
                body: JSON.stringify({
                    title:         title.value,
                    excerpt:       excerpt.value || null,
                    body:          body.value,
                    change_reason: changeReason.value || null,
                }),
            }
        );
        // Clear autosave after successful save
        await apiFetch(`/api/content/${props.contentId}/autosave`, { method: 'DELETE' }).catch(() => {});
        isDirty.value   = false;
        lastSaved.value = new Date();
        emit('saved', res?.data ?? null);
    } catch (e) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}

async function discardChanges() {
    if (!confirm('Discard all unsaved changes?')) return;
    try {
        await apiFetch(`/api/content/${props.contentId}/autosave`, { method: 'DELETE' }).catch(() => {});
    } catch {}
    emit('discarded');
}

function formatTime(d) {
    if (!d) return '';
    return d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
</script>

<template>
    <div class="bg-gray-900 rounded-xl border border-gray-800">
        <!-- Header -->
        <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-gray-800">
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-medium text-white">✏️ Edit Draft</h3>
                <span class="text-xs px-1.5 py-0.5 bg-gray-800 text-gray-400 rounded font-mono">
                    v{{ version.version_number }}
                </span>
                <span v-if="autosaving" class="text-xs text-indigo-400 animate-pulse">autosaving…</span>
                <span v-else-if="lastSaved" class="text-xs text-gray-600">
                    saved {{ formatTime(lastSaved) }}
                </span>
            </div>
            <div class="flex items-center gap-1">
                <span v-if="isDirty" class="text-xs text-amber-400 mr-2">● Unsaved</span>
                <button
                    @click="discardChanges"
                    class="text-xs px-3 py-1.5 text-gray-400 hover:text-red-400 border border-gray-700 rounded-lg hover:border-red-800/50 transition"
                >
                    Discard
                </button>
                <button
                    @click="saveDraft"
                    :disabled="saving"
                    class="text-xs px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white rounded-lg transition font-medium"
                >
                    {{ saving ? 'Saving…' : 'Save Draft' }}
                </button>
            </div>
        </div>

        <!-- Error -->
        <div v-if="error" class="mx-5 mt-3 px-3 py-2 bg-red-900/30 border border-red-500/30 rounded-lg text-red-400 text-xs">
            {{ error }}
            <button @click="error = null" class="ml-2 opacity-60 hover:opacity-100">✕</button>
        </div>

        <!-- Form -->
        <div class="p-5 space-y-4">
            <!-- Title -->
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wide block mb-1.5">Title</label>
                <input
                    v-model="title"
                    type="text"
                    placeholder="Content title…"
                    class="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-indigo-500 transition"
                />
            </div>

            <!-- Excerpt -->
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wide block mb-1.5">Excerpt</label>
                <textarea
                    v-model="excerpt"
                    rows="2"
                    placeholder="Short summary…"
                    class="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-gray-300 placeholder-gray-600 resize-y focus:outline-none focus:border-indigo-500 transition"
                />
            </div>

            <!-- Body -->
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wide block mb-1.5">Body (Markdown)</label>
                <textarea
                    v-model="body"
                    rows="16"
                    placeholder="Content body in Markdown…"
                    class="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-gray-300 font-mono placeholder-gray-600 resize-y focus:outline-none focus:border-indigo-500 transition leading-relaxed"
                />
                <p class="text-xs text-gray-600 mt-1">{{ body.length.toLocaleString() }} characters</p>
            </div>

            <!-- Change reason -->
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wide block mb-1.5">Change Reason</label>
                <input
                    v-model="changeReason"
                    type="text"
                    placeholder="Brief description of changes…"
                    class="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2.5 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-indigo-500 transition"
                />
            </div>

            <!-- Action bar -->
            <div class="flex items-center gap-3 pt-1">
                <button
                    @click="saveDraft"
                    :disabled="saving"
                    class="px-5 py-2 text-sm font-medium bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white rounded-lg transition"
                >
                    {{ saving ? '⏳ Saving…' : '💾 Save Draft' }}
                </button>
                <button
                    @click="discardChanges"
                    class="px-4 py-2 text-sm text-red-500 hover:text-red-400 transition"
                >
                    Discard Changes
                </button>
                <span v-if="isDirty" class="text-xs text-amber-400">● Unsaved changes</span>
            </div>
        </div>
    </div>
</template>
