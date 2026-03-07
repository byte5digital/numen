<script setup>
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    contents: Object,
});

function deleteContent(id) {
    if (confirm('Permanently delete this content? This will remove all versions, blocks, pipeline runs, and media associations. This cannot be undone.')) {
        router.delete(`/admin/content/${id}`);
    }
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-white">Content</h1>
                <p class="text-gray-500 mt-1">All AI-generated and human-curated content</p>
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
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Quality</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">SEO</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Author</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Published</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    <tr v-for="content in contents?.data" :key="content.id" class="hover:bg-gray-800/50">
                        <td class="px-6 py-4">
                            <Link :href="`/admin/content/${content.id}`" class="text-sm font-medium text-gray-200 hover:text-indigo-400">
                                {{ content.title || 'Untitled' }}
                            </Link>
                            <p class="text-xs text-gray-600 mt-0.5">{{ content.slug }}</p>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-400">{{ content.type }}</td>
                        <td class="px-6 py-4">
                            <span
                                class="px-2 py-1 text-xs rounded-full"
                                :class="{
                                    'bg-emerald-900/50 text-emerald-400': content.status === 'published',
                                    'bg-indigo-900/50 text-indigo-400': content.status === 'in_pipeline',
                                    'bg-amber-900/50 text-amber-400': content.status === 'review',
                                    'bg-gray-800 text-gray-400': content.status === 'draft',
                                }"
                            >
                                {{ content.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div v-if="content.quality_score" class="flex items-center gap-2">
                                <div class="w-16 h-1.5 bg-gray-800 rounded-full overflow-hidden">
                                    <div
                                        class="h-full rounded-full"
                                        :class="content.quality_score >= 80 ? 'bg-emerald-500' : content.quality_score >= 60 ? 'bg-amber-500' : 'bg-red-500'"
                                        :style="{ width: `${content.quality_score}%` }"
                                    ></div>
                                </div>
                                <span class="text-xs text-gray-500">{{ content.quality_score }}</span>
                            </div>
                            <span v-else class="text-xs text-gray-600">—</span>
                        </td>
                        <td class="px-6 py-4">
                            <span v-if="content.seo_score" class="text-xs text-gray-400">{{ content.seo_score }}</span>
                            <span v-else class="text-xs text-gray-600">—</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-xs" :class="content.author_type === 'ai_agent' ? 'text-indigo-400' : 'text-emerald-400'">
                                {{ content.author_type === 'ai_agent' ? '🤖 AI' : '👤 Human' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-500">
                            {{ content.published_at || '—' }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a :href="`/admin/content/${content.id}/edit`" class="text-xs text-indigo-500 hover:text-indigo-700 mr-3">Edit</a>
                            <button @click="deleteContent(content.id)" class="text-xs text-red-500 hover:text-red-700 font-medium">
                                Delete
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!contents?.data?.length" class="px-6 py-12 text-center">
                <p class="text-gray-600">No content yet. Create a brief to start the AI pipeline.</p>
                <Link href="/admin/briefs/create" class="mt-3 inline-block text-sm text-indigo-400 hover:text-indigo-300">
                    Create your first brief →
                </Link>
            </div>
        </div>
    </div>
</template>
