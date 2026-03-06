<script setup>
import { Link } from '@inertiajs/vue3';
import MainLayout from '../../Layouts/MainLayout.vue';

defineProps({
    pages: { type: Array, default: () => [] },
});

const statusColor = (status) =>
    status === 'published' ? 'text-emerald-400 bg-emerald-400/10' : 'text-amber-400 bg-amber-400/10';
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-white">Pages</h1>
                <p class="text-gray-500 mt-1">Manage dynamic page content and component blocks</p>
            </div>
        </div>

        <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-800 text-left">
                        <th class="px-6 py-4 text-gray-400 font-medium">Page</th>
                        <th class="px-6 py-4 text-gray-400 font-medium">Slug</th>
                        <th class="px-6 py-4 text-gray-400 font-medium">Components</th>
                        <th class="px-6 py-4 text-gray-400 font-medium">Status</th>
                        <th class="px-6 py-4 text-gray-400 font-medium">Updated</th>
                        <th class="px-6 py-4 text-gray-400 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="page in pages" :key="page.id"
                        class="border-b border-gray-800/50 hover:bg-gray-800/30 transition">
                        <td class="px-6 py-4 text-white font-medium">{{ page.title }}</td>
                        <td class="px-6 py-4 text-gray-400 font-mono text-xs">/{{ page.slug }}</td>
                        <td class="px-6 py-4 text-gray-400">{{ page.component_count }} blocks</td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 rounded-full text-xs font-medium"
                                  :class="statusColor(page.status)">
                                {{ page.status }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-500 text-xs">{{ page.updated_at }}</td>
                        <td class="px-6 py-4 text-right">
                            <Link :href="`/admin/pages/${page.id}/edit`"
                                  class="text-indigo-400 hover:text-indigo-300 text-xs font-medium">
                                Edit blocks →
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="!pages.length">
                        <td colspan="6" class="px-6 py-12 text-center text-gray-600">
                            No pages yet. Run <code class="text-indigo-400">php artisan db:seed --class=PageSeeder</code> to seed the home page.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
import MainLayout from '../../Layouts/MainLayout.vue';
export default { layout: MainLayout };
</script>
