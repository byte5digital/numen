<script setup>
import { Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';

defineProps({ briefs: Object });

// Auto-refresh every 5s
let refreshInterval = null;
onMounted(() => {
    refreshInterval = setInterval(() => {
        router.reload({ only: ['briefs'], preserveScroll: true });
    }, 5000);
});
onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval);
});
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-white">Content Briefs</h1>
                <p class="text-gray-500 mt-1">Submit briefs to start the AI content pipeline
                    <span class="inline-flex items-center gap-1.5 ml-2 text-xs text-gray-600">
                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                        Auto-refreshing
                    </span>
                </p>
            </div>
            <Link href="/admin/briefs/create" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-500 transition">
                + New Brief
            </Link>
        </div>

        <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
            <table class="w-full">
                <thead class="border-b border-gray-800">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Source</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Priority</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Pipeline</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    <tr v-for="brief in briefs?.data" :key="brief.id"
                        class="hover:bg-gray-800/50 transition-colors"
                        :class="{
                            'border-l-4 border-l-purple-500 bg-purple-900/5': brief.content_id,
                            'border-l-4 border-l-indigo-500 bg-indigo-900/5': !brief.content_id && brief.status === 'processing',
                        }">
                        <td class="px-6 py-4">
                            <Link :href="`/admin/briefs/${brief.id}`" class="text-sm font-medium text-gray-200 hover:text-indigo-400">
                                {{ brief.title }}
                            </Link>
                            <span v-if="brief.content_id"
                                  class="ml-2 text-xs px-1.5 py-0.5 rounded bg-purple-900/30 text-purple-300">
                                ✏️ update
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-400">{{ brief.content_type_slug }}</td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-0.5 text-xs rounded-full"
                                  :class="brief.source === 'update_brief' ? 'bg-purple-900/30 text-purple-300' : 'bg-gray-800 text-gray-400'">
                                {{ brief.source }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs rounded-full"
                                :class="{
                                    'bg-red-900/50 text-red-400': brief.priority === 'urgent',
                                    'bg-amber-900/50 text-amber-400': brief.priority === 'high',
                                    'bg-gray-800 text-gray-400': brief.priority === 'normal',
                                    'bg-gray-800/50 text-gray-500': brief.priority === 'low',
                                }"
                            >{{ brief.priority }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs rounded-full"
                                :class="{
                                    'bg-emerald-900/50 text-emerald-400': brief.status === 'completed',
                                    'bg-indigo-900/50 text-indigo-400': brief.status === 'processing',
                                    'bg-amber-900/50 text-amber-400': brief.status === 'in_review',
                                    'bg-gray-800 text-gray-400': brief.status === 'pending',
                                    'bg-red-900/50 text-red-400': brief.status === 'failed' || brief.status === 'cancelled',
                                }">
                                {{ brief.status }}
                                <span v-if="brief.status === 'processing'" class="inline-block w-1.5 h-1.5 bg-current rounded-full animate-pulse ml-1"></span>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-500">
                            {{ brief.pipeline_run?.current_stage || '—' }}
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-500">{{ brief.created_at }}</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!briefs?.data?.length" class="px-6 py-12 text-center">
                <p class="text-gray-600">No briefs yet.</p>
                <Link href="/admin/briefs/create" class="mt-3 inline-block text-sm text-indigo-400 hover:text-indigo-300">
                    Create your first brief →
                </Link>
            </div>
        </div>
    </div>
</template>
