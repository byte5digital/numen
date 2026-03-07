<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    contentId: { type: [String, Number], required: true },
    version:   { type: Object, required: true },
});

const emit = defineEmits(['scheduled', 'cancelled']);

// ── State ──────────────────────────────────────────────────────────────────
const publishAt = ref('');
const notes     = ref('');
const loading   = ref(false);
const error     = ref(null);
const countdown = ref('');
let   countdownTimer = null;

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

const isScheduled = computed(() => !!props.version.scheduled_at);

const scheduledDate = computed(() => {
    if (!props.version.scheduled_at) return null;
    return new Date(props.version.scheduled_at);
});

// Countdown timer
function updateCountdown() {
    if (!scheduledDate.value) {
        countdown.value = '';
        return;
    }
    const now  = Date.now();
    const diff = scheduledDate.value.getTime() - now;

    if (diff <= 0) {
        countdown.value = 'Publishing now…';
        return;
    }

    const days    = Math.floor(diff / 86400000);
    const hours   = Math.floor((diff % 86400000) / 3600000);
    const minutes = Math.floor((diff % 3600000) / 60000);
    const seconds = Math.floor((diff % 60000) / 1000);

    if (days > 0)    countdown.value = `${days}d ${hours}h ${minutes}m`;
    else if (hours > 0) countdown.value = `${hours}h ${minutes}m ${seconds}s`;
    else             countdown.value = `${minutes}m ${seconds}s`;
}

onMounted(() => {
    // Set default publish time to 1 hour from now
    const d = new Date(Date.now() + 3600000);
    publishAt.value = new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);

    updateCountdown();
    countdownTimer = setInterval(updateCountdown, 1000);
});

onUnmounted(() => {
    clearInterval(countdownTimer);
});

// ── Actions ──────────────────────────────────────────────────────────────────
async function schedule() {
    if (!publishAt.value) return;
    loading.value = true;
    error.value   = null;
    try {
        const res = await apiFetch(
            `/api/content/${props.contentId}/versions/${props.version.id}/schedule`,
            {
                method: 'POST',
                body: JSON.stringify({
                    publish_at: new Date(publishAt.value).toISOString(),
                    notes: notes.value || null,
                }),
            }
        );
        emit('scheduled', res?.data ?? null);
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function cancelSchedule() {
    if (!confirm('Cancel the scheduled publish?')) return;
    loading.value = true;
    error.value   = null;
    try {
        await apiFetch(
            `/api/content/${props.contentId}/versions/${props.version.id}/schedule`,
            { method: 'DELETE' }
        );
        emit('cancelled');
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

function formatScheduledDate(dt) {
    return new Date(dt).toLocaleString('en-GB', {
        weekday: 'short', day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}
</script>

<template>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-sm font-medium text-white mb-4">⏰ Schedule Publishing</h3>

        <!-- Error -->
        <div v-if="error" class="mb-3 px-3 py-2 bg-red-900/30 border border-red-500/30 rounded-lg text-red-400 text-xs">
            {{ error }}
            <button @click="error = null" class="ml-2 opacity-60 hover:opacity-100">✕</button>
        </div>

        <!-- Already scheduled -->
        <div v-if="isScheduled" class="space-y-3">
            <div class="flex items-start gap-3 p-3 bg-amber-900/20 border border-amber-500/30 rounded-lg">
                <span class="text-amber-400 text-lg">⏰</span>
                <div>
                    <p class="text-xs font-medium text-amber-300">Scheduled to publish</p>
                    <p class="text-sm text-white font-semibold mt-0.5">
                        {{ formatScheduledDate(version.scheduled_at) }}
                    </p>
                    <p v-if="countdown" class="text-xs text-amber-400 mt-1 font-mono">
                        ⏱ {{ countdown }}
                    </p>
                </div>
            </div>
            <button
                @click="cancelSchedule"
                :disabled="loading"
                class="w-full py-2 text-sm text-red-400 border border-red-800/50 rounded-lg hover:bg-red-900/20 hover:border-red-700 disabled:opacity-50 transition"
            >
                {{ loading ? 'Cancelling…' : '✕ Cancel Schedule' }}
            </button>
        </div>

        <!-- Schedule form -->
        <div v-else class="space-y-3">
            <div>
                <label class="text-xs text-gray-500 block mb-1">Publish Date & Time</label>
                <input
                    v-model="publishAt"
                    type="datetime-local"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500"
                />
            </div>
            <div>
                <label class="text-xs text-gray-500 block mb-1">Notes (optional)</label>
                <input
                    v-model="notes"
                    type="text"
                    placeholder="e.g. Campaign launch…"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-600 focus:outline-none focus:border-indigo-500"
                />
            </div>
            <button
                @click="schedule"
                :disabled="!publishAt || loading"
                class="w-full py-2 text-sm font-medium bg-amber-600 hover:bg-amber-500 disabled:opacity-50 text-white rounded-lg transition"
            >
                {{ loading ? '⏳ Scheduling…' : '⏰ Schedule Publish' }}
            </button>
        </div>
    </div>
</template>
