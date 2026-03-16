<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';

interface Props {
    spaceId: string;
}

const props = defineProps<Props>();

interface CompetitorSource {
    id: string;
    name: string;
    url: string;
    feed_url: string | null;
    crawler_type: string;
    is_active: boolean;
    crawl_interval_minutes: number;
    last_crawled_at: string | null;
    error_count: number;
}

const sources = ref<CompetitorSource[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);
const showForm = ref(false);
const crawlingId = ref<string | null>(null);

const form = ref({
    name: '',
    url: '',
    feed_url: '',
    crawler_type: 'rss' as 'rss' | 'sitemap' | 'scrape' | 'api',
    is_active: true,
    crawl_interval_minutes: 60,
});

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function fetchSources(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const res = await fetch(
            `/api/v1/competitor/sources?space_id=${props.spaceId}&per_page=50`,
            {
                credentials: 'include',
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getXsrfToken() },
            },
        );
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const json = await res.json();
        sources.value = json.data as CompetitorSource[];
    } catch (e: unknown) {
        error.value = e instanceof Error ? e.message : String(e);
    } finally {
        loading.value = false;
    }
}

async function addSource(): Promise<void> {
    try {
        const res = await fetch('/api/v1/competitor/sources', {
            method: 'POST',
            credentials: 'include',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({ ...form.value, space_id: props.spaceId }),
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        showForm.value = false;
        form.value = { name: '', url: '', feed_url: '', crawler_type: 'rss', is_active: true, crawl_interval_minutes: 60 };
        await fetchSources();
    } catch (e: unknown) {
        error.value = e instanceof Error ? e.message : String(e);
    }
}

async function deleteSource(id: string): Promise<void> {
    if (!confirm('Delete this competitor source?')) return;
    try {
        await fetch(`/api/v1/competitor/sources/${id}`, {
            method: 'DELETE',
            credentials: 'include',
            headers: { 'X-XSRF-TOKEN': getXsrfToken() },
        });
        await fetchSources();
    } catch (e: unknown) {
        error.value = e instanceof Error ? e.message : String(e);
    }
}

async function triggerCrawl(id: string): Promise<void> {
    crawlingId.value = id;
    try {
        await fetch(`/api/v1/competitor/sources/${id}/crawl`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'X-XSRF-TOKEN': getXsrfToken() },
        });
    } finally {
        crawlingId.value = null;
    }
}

function crawlerTypeLabel(type: string): string {
    return { rss: 'RSS', sitemap: 'Sitemap', scrape: 'Scrape', api: 'API' }[type] ?? type;
}

onMounted(fetchSources);
watch(() => props.spaceId, fetchSources);
</script>

<template>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                <span>🕷️</span> Competitor Sources
            </h2>
            <button
                class="text-xs bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1 rounded transition"
                @click="showForm = !showForm"
            >
                {{ showForm ? 'Cancel' : '+ Add Source' }}
            </button>
        </div>

        <!-- Add Form -->
        <div v-if="showForm" class="mb-4 bg-gray-800 rounded-lg p-4 space-y-3">
            <div>
                <label class="block text-xs text-gray-400 mb-1">Name</label>
                <input
                    v-model="form.name"
                    class="w-full bg-gray-700 text-white text-sm rounded px-3 py-2 border border-gray-600 focus:outline-none focus:border-indigo-500"
                    placeholder="e.g. Competitor Blog"
                />
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">URL</label>
                <input
                    v-model="form.url"
                    class="w-full bg-gray-700 text-white text-sm rounded px-3 py-2 border border-gray-600 focus:outline-none focus:border-indigo-500"
                    placeholder="https://competitor.com"
                />
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Feed URL (optional)</label>
                <input
                    v-model="form.feed_url"
                    class="w-full bg-gray-700 text-white text-sm rounded px-3 py-2 border border-gray-600 focus:outline-none focus:border-indigo-500"
                    placeholder="https://competitor.com/feed"
                />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Crawler Type</label>
                    <select
                        v-model="form.crawler_type"
                        class="w-full bg-gray-700 text-white text-sm rounded px-3 py-2 border border-gray-600"
                    >
                        <option value="rss">RSS</option>
                        <option value="sitemap">Sitemap</option>
                        <option value="scrape">Scrape</option>
                        <option value="api">API</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Interval (min)</label>
                    <input
                        v-model.number="form.crawl_interval_minutes"
                        type="number"
                        min="5"
                        max="10080"
                        class="w-full bg-gray-700 text-white text-sm rounded px-3 py-2 border border-gray-600"
                    />
                </div>
            </div>
            <button
                class="w-full bg-indigo-600 hover:bg-indigo-500 text-white text-sm rounded py-2 transition"
                @click="addSource"
            >
                Add Source
            </button>
        </div>

        <!-- Error -->
        <div v-if="error" class="text-red-400 text-sm mb-3">{{ error }}</div>

        <!-- Loading -->
        <div v-if="loading" class="animate-pulse space-y-2">
            <div v-for="i in 3" :key="i" class="h-12 bg-gray-800 rounded"></div>
        </div>

        <!-- Source List -->
        <div v-else class="space-y-2">
            <div v-if="sources.length === 0" class="text-gray-500 text-sm text-center py-4">
                No competitor sources yet.
            </div>
            <div
                v-for="source in sources"
                :key="source.id"
                class="flex items-center justify-between bg-gray-800 rounded-lg px-4 py-3"
            >
                <div class="min-w-0">
                    <div class="text-white text-sm font-medium truncate">{{ source.name }}</div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-xs bg-gray-700 text-gray-300 px-2 py-0.5 rounded">
                            {{ crawlerTypeLabel(source.crawler_type) }}
                        </span>
                        <span
                            :class="['text-xs px-2 py-0.5 rounded', source.is_active ? 'bg-green-900 text-green-400' : 'bg-gray-700 text-gray-400']"
                        >
                            {{ source.is_active ? 'Active' : 'Inactive' }}
                        </span>
                        <span v-if="source.error_count > 0" class="text-xs text-red-400">
                            {{ source.error_count }} error(s)
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2 ml-3 shrink-0">
                    <button
                        :disabled="crawlingId === source.id"
                        class="text-xs text-gray-400 hover:text-white transition disabled:opacity-50"
                        @click="triggerCrawl(source.id)"
                    >
                        {{ crawlingId === source.id ? '...' : '↺' }}
                    </button>
                    <button
                        class="text-xs text-red-400 hover:text-red-300 transition"
                        @click="deleteSource(source.id)"
                    >
                        ✕
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
