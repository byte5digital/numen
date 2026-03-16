<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import MainLayout from '../../../Layouts/MainLayout.vue'

interface Space {
    id: string
    name: string
    slug: string
    description: string | null
    default_locale: string
    created_at: string
    is_current: boolean
}

const props = defineProps<{
    spaces: Space[]
}>()

const page = usePage()
const flash = computed(() => (page.props.flash as { success?: string; error?: string }) ?? {})

const confirmDelete = ref<string | null>(null)

function deleteSpace(id: string) {
    router.delete(`/admin/spaces/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
            confirmDelete.value = null
        },
    })
}
</script>

<template>
    <MainLayout>
        <Head title="Spaces" />

        <div class="max-w-5xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-white">Spaces</h1>
                    <p class="text-sm text-gray-400 mt-1">Manage your content spaces and configurations</p>
                </div>
                <a
                    href="/admin/spaces/create"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors"
                >
                    + New Space
                </a>
            </div>

            <!-- Flash messages -->
            <div v-if="flash.success" class="mb-4 px-4 py-3 bg-green-500/15 border border-green-500/30 text-green-400 rounded-lg text-sm">
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="mb-4 px-4 py-3 bg-red-500/15 border border-red-500/30 text-red-400 rounded-lg text-sm">
                {{ flash.error }}
            </div>

            <!-- Spaces table -->
            <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-800">
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Name</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Slug</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Locale</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Created</th>
                            <th class="text-right px-6 py-3 text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <tr v-for="space in spaces" :key="space.id" class="hover:bg-gray-800/40 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-white font-medium">{{ space.name }}</span>
                                    <span
                                        v-if="space.is_current"
                                        class="px-2 py-0.5 text-xs bg-indigo-500/20 text-indigo-400 rounded-full border border-indigo-500/30"
                                    >
                                        Current
                                    </span>
                                </div>
                                <p v-if="space.description" class="text-xs text-gray-500 mt-0.5">{{ space.description }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <code class="text-xs text-gray-400 bg-gray-800 px-2 py-1 rounded">{{ space.slug }}</code>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-gray-300">{{ space.default_locale }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-gray-500">{{ new Date(space.created_at).toLocaleDateString() }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a
                                        :href="`/admin/spaces/${space.id}/edit`"
                                        class="px-3 py-1.5 text-xs text-gray-300 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors"
                                    >
                                        Edit
                                    </a>
                                    <button
                                        @click="confirmDelete = space.id"
                                        class="px-3 py-1.5 text-xs text-red-400 bg-red-500/10 hover:bg-red-500/20 rounded-lg transition-colors"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="spaces.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">No spaces found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Delete Confirm Dialog -->
        <div v-if="confirmDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
            <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-bold text-white mb-2">Delete Space</h3>
                <p class="text-sm text-gray-400 mb-6">Are you sure you want to delete this space? This action cannot be undone.</p>
                <div class="flex gap-3 justify-end">
                    <button
                        @click="confirmDelete = null"
                        class="px-4 py-2 text-sm text-gray-300 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        @click="deleteSpace(confirmDelete)"
                        class="px-4 py-2 text-sm text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors"
                    >
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
