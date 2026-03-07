<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    contentId: { type: [String, Number], required: true },
    versions:  { type: Array, default: () => [] },
});

// ── State ──────────────────────────────────────────────────────────────────
const fromId = ref(null);
const toId   = ref(null);
const diff   = ref(null);
const loading = ref(false);
const error   = ref(null);

// ── Helpers ─────────────────────────────────────────────────────────────────
async function csrfCookie() {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
}

function xsrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

const sortedVersions = computed(() =>
    [...props.versions].sort((a, b) => b.version_number - a.version_number)
);

function versionLabel(v) {
    return `v${v.version_number}${v.label ? ` — ${v.label}` : ''} (${v.status})`;
}

// ── Fetch diff ───────────────────────────────────────────────────────────────
async function fetchDiff() {
    if (!fromId.value || !toId.value) return;
    if (fromId.value === toId.value) {
        error.value = 'Select two different versions to compare.';
        return;
    }
    loading.value = true;
    error.value   = null;
    diff.value    = null;

    try {
        await csrfCookie();
        const res = await fetch(
            `/api/content/${props.contentId}/diff?from=${fromId.value}&to=${toId.value}`,
            {
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
            }
        );
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        diff.value = await res.json();
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

// ── Render a unified diff hunk into highlighted HTML ──────────────────────────
// The API may return a `diff` string (unified diff format) or structured data.
// We'll handle both by rendering the raw unified diff with line-by-line colouring.
function renderDiffLines(raw) {
    if (!raw) return [];
    return String(raw).split('\n').map((line) => {
        if (line.startsWith('+') && !line.startsWith('+++')) {
            return { type: 'add', text: line };
        }
        if (line.startsWith('-') && !line.startsWith('---')) {
            return { type: 'remove', text: line };
        }
        if (line.startsWith('@@')) {
            return { type: 'hunk', text: line };
        }
        return { type: 'context', text: line };
    });
}

const diffLines = computed(() => {
    if (!diff.value) return { body: [], title: [], excerpt: [] };

    // The API might return: { body: "...", title: "...", excerpt: "..." }
    // or a top-level { diff: "..." }
    const d = diff.value?.data ?? diff.value;
    return {
        title:   renderDiffLines(d?.title_diff   ?? d?.title   ?? ''),
        excerpt: renderDiffLines(d?.excerpt_diff  ?? d?.excerpt ?? ''),
        body:    renderDiffLines(d?.body_diff     ?? d?.body    ?? d?.diff ?? ''),
    };
}); 

function lineClass(type) {
    switch (type) {
        case 'add':     return 'bg-emerald-900/30 text-emerald-300';
        case 'remove':  return 'bg-red-900/30 text-red-300 line-through';
        case 'hunk':    return 'bg-indigo-900/20 text-indigo-400 font-medium';
        default:        return 'text-gray-400';
    }
}
</script>

<template>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-sm font-medium text-white mb-4">🔀 Compare Versions</h3>

        <!-- Selectors -->
        <div class="flex items-center gap-3 mb-4 flex-wrap">
            <div class="flex-1 min-w-[140px]">
                <label class="text-xs text-gray-500 block mb-1">From</label>
                <select
                    v-model="fromId"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-2 py-1.5 text-sm text-white focus:outline-none focus:border-indigo-500"
                >
                    <option value="" disabled selected>Select version</option>
                    <option v-for="v in sortedVersions" :key="v.id" :value="v.id">
                        {{ versionLabel(v) }}
                    </option>
                </select>
            </div>

            <span class="text-gray-600 mt-4">→</span>

            <div class="flex-1 min-w-[140px]">
                <label class="text-xs text-gray-500 block mb-1">To</label>
                <select
                    v-model="toId"
                    class="w-full bg-gray-800 border border-gray-700 rounded-lg px-2 py-1.5 text-sm text-white focus:outline-none focus:border-indigo-500"
                >
                    <option value="" disabled selected>Select version</option>
                    <option v-for="v in sortedVersions" :key="v.id" :value="v.id">
                        {{ versionLabel(v) }}
                    </option>
                </select>
            </div>

            <div class="mt-4">
                <button
                    @click="fetchDiff"
                    :disabled="!fromId || !toId || loading"
                    class="px-4 py-1.5 text-sm bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white rounded-lg transition"
                >
                    {{ loading ? 'Comparing…' : 'Compare' }}
                </button>
            </div>
        </div>

        <!-- Error -->
        <div v-if="error" class="mb-3 px-3 py-2 bg-red-900/30 border border-red-500/30 rounded-lg text-red-400 text-xs">
            {{ error }}
        </div>

        <!-- Loading -->
        <div v-if="loading" class="text-center py-8 text-gray-500 text-sm">
            Loading diff…
        </div>

        <!-- Empty state -->
        <div v-else-if="!diff && !error" class="text-center py-6 text-gray-600 text-xs">
            Select two versions and click Compare to see the differences.
        </div>

        <!-- Diff output -->
        <div v-else-if="diff" class="space-y-4">

            <!-- Legend -->
            <div class="flex items-center gap-4 text-xs">
                <span class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded-sm bg-emerald-900/50 border border-emerald-500/30"></span>
                    <span class="text-gray-500">Added</span>
                </span>
                <span class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded-sm bg-red-900/50 border border-red-500/30"></span>
                    <span class="text-gray-500">Removed</span>
                </span>
            </div>

            <!-- Title diff -->
            <div v-if="diffLines.title.length" class="space-y-1">
                <p class="text-xs text-gray-500 uppercase tracking-wide font-medium mb-1">Title</p>
                <div class="bg-gray-950 rounded-lg p-3 overflow-x-auto">
                    <div v-for="(line, i) in diffLines.title" :key="i"
                         class="text-xs font-mono px-1 py-0.5 rounded" :class="lineClass(line.type)">
                        {{ line.text }}
                    </div>
                </div>
            </div>

            <!-- Excerpt diff -->
            <div v-if="diffLines.excerpt.length" class="space-y-1">
                <p class="text-xs text-gray-500 uppercase tracking-wide font-medium mb-1">Excerpt</p>
                <div class="bg-gray-950 rounded-lg p-3 overflow-x-auto">
                    <div v-for="(line, i) in diffLines.excerpt" :key="i"
                         class="text-xs font-mono px-1 py-0.5 rounded" :class="lineClass(line.type)">
                        {{ line.text }}
                    </div>
                </div>
            </div>

            <!-- Body diff -->
            <div v-if="diffLines.body.length" class="space-y-1">
                <p class="text-xs text-gray-500 uppercase tracking-wide font-medium mb-1">Body</p>
                <div class="bg-gray-950 rounded-lg p-3 overflow-x-auto max-h-[500px] overflow-y-auto">
                    <div v-for="(line, i) in diffLines.body" :key="i"
                         class="text-xs font-mono px-1 py-0.5 rounded whitespace-pre-wrap break-all"
                         :class="lineClass(line.type)">
                        {{ line.text }}
                    </div>
                </div>
            </div>

            <!-- No diff message -->
            <div v-if="!diffLines.body.length && !diffLines.title.length && !diffLines.excerpt.length"
                 class="text-center py-4 text-gray-500 text-xs">
                No differences found between these versions.
            </div>
        </div>
    </div>
</template>
