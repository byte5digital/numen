<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
    spaceId: { type: String, required: true },
});

const tests = ref([]);
const loading = ref(false);
const showCreate = ref(false);
const newTest = ref({ name: '', content_id: '', variants: [] });

async function fetchTests() {
    loading.value = true;
    try {
        const res = await fetch(`/api/v1/spaces/${props.spaceId}/ab-tests`, { credentials: 'include' });
        if (res.ok) {
            const json = await res.json();
            tests.value = json.data ?? json ?? [];
        }
    } catch (e) {
        console.error('Failed to fetch A/B tests', e);
    } finally {
        loading.value = false;
    }
}

async function endTest(testId) {
    if (!confirm('End this A/B test?')) return;
    try {
        await fetch(`/api/v1/spaces/${props.spaceId}/ab-tests/${testId}/end`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrf() },
        });
        await fetchTests();
    } catch (e) {
        console.error('Failed to end test', e);
    }
}

async function createTest() {
    try {
        await fetch(`/api/v1/spaces/${props.spaceId}/ab-tests`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrf() },
            body: JSON.stringify(newTest.value),
        });
        showCreate.value = false;
        newTest.value = { name: '', content_id: '', variants: [] };
        await fetchTests();
    } catch (e) {
        console.error('Failed to create test', e);
    }
}

function getCsrf() {
    return document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '';
}

function statusBadge(status) {
    const map = {
        running: 'bg-emerald-500/20 text-emerald-300',
        active: 'bg-emerald-500/20 text-emerald-300',
        completed: 'bg-gray-700 text-gray-300',
        ended: 'bg-gray-700 text-gray-300',
    };
    return map[status] ?? 'bg-gray-700 text-gray-400';
}

function significanceLabel(test) {
    if (!test.results?.significant) return null;
    return test.results.significant ? '✓ Significant' : '⏳ Gathering data';
}

onMounted(fetchTests);
</script>

<template>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white">A/B Tests</h3>
            <button
                @click="showCreate = !showCreate"
                class="px-3 py-1.5 text-xs font-medium bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg transition"
            >
                + New Test
            </button>
        </div>

        <!-- Create form -->
        <div v-if="showCreate" class="mb-4 p-4 bg-gray-950 rounded-lg border border-gray-800 space-y-3">
            <input v-model="newTest.name" placeholder="Test name"
                   class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:outline-none" />
            <input v-model="newTest.content_id" placeholder="Content ID"
                   class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-indigo-500 focus:outline-none" />
            <div class="flex gap-2">
                <button @click="createTest" class="px-3 py-1.5 text-xs bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg">Create</button>
                <button @click="showCreate = false" class="px-3 py-1.5 text-xs text-gray-400 hover:text-white">Cancel</button>
            </div>
        </div>

        <div v-if="loading" class="py-8 text-center text-gray-500 text-sm">Loading tests…</div>

        <div v-else-if="!tests.length" class="py-8 text-center text-gray-600 text-sm">
            No A/B tests yet. Create one to get started.
        </div>

        <div v-else class="space-y-3">
            <div v-for="test in tests" :key="test.id"
                 class="p-4 bg-gray-950 rounded-lg border border-gray-800">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-white">{{ test.name }}</span>
                        <span class="px-2 py-0.5 text-xs rounded-full" :class="statusBadge(test.status)">
                            {{ test.status }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span v-if="significanceLabel(test)" class="text-xs text-emerald-400">
                            {{ significanceLabel(test) }}
                        </span>
                        <button
                            v-if="test.status === 'running' || test.status === 'active'"
                            @click="endTest(test.id)"
                            class="px-2 py-1 text-xs text-red-400 hover:text-red-300 transition"
                        >
                            End Test
                        </button>
                    </div>
                </div>

                <!-- Variant results -->
                <div v-if="test.variants?.length" class="mt-3 space-y-2">
                    <div v-for="variant in test.variants" :key="variant.id ?? variant.name"
                         class="flex items-center justify-between text-xs">
                        <span class="text-gray-400">
                            {{ variant.name ?? variant.label ?? 'Variant' }}
                            <span v-if="test.results?.winner === variant.id || test.results?.winner === variant.name"
                                  class="ml-1 text-emerald-400">👑 Winner</span>
                        </span>
                        <div class="flex items-center gap-4 text-gray-500">
                            <span>Views: {{ variant.views ?? variant.impressions ?? 0 }}</span>
                            <span>Conv: {{ variant.conversions ?? 0 }}</span>
                            <span v-if="variant.conversion_rate != null">
                                Rate: {{ (variant.conversion_rate * 100).toFixed(1) }}%
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
