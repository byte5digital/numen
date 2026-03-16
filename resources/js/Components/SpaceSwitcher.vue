<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

interface SpaceRef {
    id: string
    name: string
    slug: string
}

const page = usePage()
const currentSpace = computed(() => page.props.currentSpace as SpaceRef | null)
const spaces = computed(() => (page.props.spaces as SpaceRef[]) ?? [])

const open = ref(false)

function switchSpace(spaceId: string) {
    open.value = false
    router.post('/admin/spaces/switch', { space_id: spaceId }, {
        preserveScroll: false,
        onSuccess: () => window.location.reload(),
    })
}
</script>

<template>
    <div class="relative" v-if="currentSpace || spaces.length > 0">
        <button @click="open = !open" class="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-300 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors">
            <span class="text-base">🌐</span>
            <span class="max-w-[120px] truncate">{{ currentSpace?.name ?? 'Select Space' }}</span>
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        <div v-if="open" class="absolute right-0 mt-2 w-52 bg-gray-900 border border-gray-700 rounded-xl shadow-xl z-50">
            <div class="p-1">
                <button v-for="space in spaces" :key="space.id"
                    @click="switchSpace(space.id)"
                    class="w-full text-left px-3 py-2 text-sm rounded-lg transition-colors"
                    :class="space.id === currentSpace?.id ? 'bg-indigo-500/15 text-indigo-400' : 'text-gray-300 hover:bg-gray-800'">
                    <span class="flex items-center gap-2">
                        <span v-if="space.id === currentSpace?.id" class="text-indigo-400">✓</span>
                        <span v-else class="w-4 inline-block"></span>
                        {{ space.name }}
                    </span>
                </button>
            </div>
            <div class="border-t border-gray-800 p-1">
                <a href="/admin/spaces" class="block w-full text-left px-3 py-2 text-xs text-gray-500 hover:text-gray-300 rounded-lg transition-colors">Manage Spaces →</a>
            </div>
        </div>
    </div>
</template>
