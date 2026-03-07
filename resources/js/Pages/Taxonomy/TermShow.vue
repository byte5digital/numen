<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    term: { type: Object, required: true },
    content: { type: Object, required: true },
    includeDescendants: { type: Boolean, default: false },
});

const showDescendants = ref(props.includeDescendants);

function toggleDescendants() {
    showDescendants.value = !showDescendants.value;
    router.get(
        `/admin/taxonomy/terms/${props.term.id}`,
        { descendants: showDescendants.value ? 1 : 0 },
        { preserveScroll: true, preserveState: false }
    );
}

const statusColors = {
    published: 'bg-emerald-900/50 text-emerald-400',
    draft: 'bg-gray-800 text-gray-400',
    archived: 'bg-red-900/30 text-red-400',
    in_pipeline: 'bg-indigo-900/50 text-indigo-400',
    review: 'bg-amber-900/50 text-amber-400',
};
</script>

<template>
    <div>
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
            <Link href="/admin/taxonomy" class="hover:text-indigo-400 transition">Taxonomy</Link>
            <span>/</span>
            <Link :href="`/admin/taxonomy/${term.vocabulary.id}`" class="hover:text-indigo-400 transition">
                {{ term.vocabulary.name }}
            </Link>
            <span>/</span>
            <span class="text-gray-300">{{ term.name }}</span>
        </div>

        <!-- Header -->
        <div class="flex items-start justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-white">{{ term.name }}</h1>
                <p class="text-gray-500 font-mono text-sm mt-1">{{ term.slug }}</p>
                <p v-if="term.description" class="text-gray-400 text-sm mt-2 max-w-xl">{{ term.description }}</p>
            </div>
        </div>

        <!-- Term Details -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                <p class="text-xs text-gray-500 mb-1">Content Count</p>
                <p class="text-2xl font-bold text-white">{{ term.content_count }}</p>
            </div>
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                <p class="text-xs text-gray-500 mb-1">Depth</p>
                <p class="text-2xl font-bold text-white">{{ term.depth }}</p>
            </div>
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                <p class="text-xs text-gray-500 mb-1">Vocabulary</p>
                <p class="text-sm font-medium text-white truncate">{{ term.vocabulary.name }}</p>
            </div>
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                <p class="text-xs text-gray-500 mb-1">Children</p>
                <p class="text-2xl font-bold text-white">{{ term.children.length }}</p>
            </div>
        </div>

        <!-- Child Terms -->
        <div v-if="term.children.length" class="mb-8 bg-gray-900 rounded-xl border border-gray-800 p-5">
            <h2 class="text-sm font-semibold text-white mb-3">Child Terms</h2>
            <div class="flex flex-wrap gap-2">
                <Link
                    v-for="child in term.children"
                    :key="child.id"
                    :href="`/admin/taxonomy/terms/${child.id}`"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg text-sm text-gray-300 hover:text-white transition"
                >
                    {{ child.name }}
                    <span v-if="child.content_count > 0" class="text-xs text-gray-500">{{ child.content_count }}</span>
                </Link>
            </div>
        </div>

        <!-- Assigned Content -->
        <div class="bg-gray-900 rounded-xl border border-gray-800">
            <div class="px-6 py-4 border-b border-gray-800 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-white">
                    Assigned Content
                    <span class="ml-2 text-xs text-gray-500">({{ content.total }} total)</span>
                </h2>

                <!-- Descendants toggle -->
                <label v-if="term.vocabulary.hierarchy" class="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        :checked="showDescendants"
                        @change="toggleDescendants"
                        class="rounded border-gray-600 bg-gray-800 text-indigo-600"
                    />
                    <span class="text-xs text-gray-400">Include child term content</span>
                </label>
            </div>

            <!-- Table -->
            <div v-if="content.data.length">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-800 text-xs text-gray-500 uppercase tracking-wide">
                            <th class="text-left px-6 py-3">Title</th>
                            <th class="text-left px-6 py-3">Status</th>
                            <th class="text-left px-6 py-3">Type</th>
                            <th class="text-left px-6 py-3">Assignment</th>
                            <th class="text-left px-6 py-3">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in content.data"
                            :key="item.id"
                            class="border-b border-gray-800/50 hover:bg-gray-800/30 transition"
                        >
                            <td class="px-6 py-3">
                                <Link
                                    :href="`/admin/content/${item.id}`"
                                    class="text-indigo-400 hover:text-indigo-300 font-medium transition"
                                >
                                    {{ item.title }}
                                </Link>
                                <p class="text-xs text-gray-600 font-mono mt-0.5">{{ item.slug }}</p>
                            </td>
                            <td class="px-6 py-3">
                                <span
                                    class="px-2 py-0.5 rounded-full text-xs"
                                    :class="statusColors[item.status] ?? 'bg-gray-800 text-gray-400'"
                                >
                                    {{ item.status }}
                                </span>
                            </td>
                            <td class="px-6 py-3 text-gray-400 text-xs font-mono">{{ item.type }}</td>
                            <td class="px-6 py-3">
                                <span v-if="item.auto_assigned" class="inline-flex items-center gap-1 text-xs text-purple-400">
                                    🤖
                                    <span v-if="item.confidence !== null">
                                        {{ Math.round(item.confidence * 100) }}%
                                    </span>
                                    <span class="text-gray-600">AI</span>
                                </span>
                                <span v-else class="text-xs text-gray-500">Manual</span>
                            </td>
                            <td class="px-6 py-3 text-gray-500 text-xs">
                                {{ item.published_at ?? item.created_at }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="content.last_page > 1" class="px-6 py-4 border-t border-gray-800 flex items-center justify-between">
                    <p class="text-xs text-gray-500">
                        Showing {{ content.from }}–{{ content.to }} of {{ content.total }}
                    </p>
                    <div class="flex gap-2">
                        <Link
                            v-if="content.prev_page_url"
                            :href="content.prev_page_url"
                            class="px-3 py-1.5 text-xs bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg transition"
                        >
                            ← Previous
                        </Link>
                        <Link
                            v-if="content.next_page_url"
                            :href="content.next_page_url"
                            class="px-3 py-1.5 text-xs bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg transition"
                        >
                            Next →
                        </Link>
                    </div>
                </div>
            </div>

            <div v-else class="px-6 py-12 text-center text-gray-600 text-sm">
                No content assigned to this term yet.
            </div>
        </div>
    </div>
</template>

<script>
import MainLayout from '../../Layouts/MainLayout.vue';
export default { layout: MainLayout };
</script>
