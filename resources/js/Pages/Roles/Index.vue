<script setup>
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    roles: { type: Array, required: true },
});

function deleteRole(role) {
    if (!confirm(`Delete role "${role.name}"? This cannot be undone.`)) return;
    router.delete(`/admin/roles/${role.id}`);
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-white">Roles &amp; Permissions</h1>
                <p class="text-gray-500 mt-1">Manage access control roles for your team.</p>
            </div>
            <Link
                href="/admin/roles/create"
                class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg transition"
            >
                <span>+</span> New Role
            </Link>
        </div>

        <div class="space-y-3">
            <div
                v-for="role in roles"
                :key="role.id"
                class="bg-gray-900 border border-gray-800 rounded-xl p-5 flex items-start justify-between gap-4"
            >
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-white font-semibold">{{ role.name }}</span>
                        <span
                            v-if="role.is_system"
                            class="text-xs bg-indigo-900 text-indigo-300 px-2 py-0.5 rounded-full"
                        >system</span>
                    </div>
                    <p v-if="role.description" class="text-gray-500 text-sm mb-2">{{ role.description }}</p>
                    <div class="flex flex-wrap gap-1.5 mt-2">
                        <span
                            v-for="perm in (role.permissions || []).slice(0, 8)"
                            :key="perm"
                            class="text-xs bg-gray-800 text-gray-400 px-2 py-0.5 rounded font-mono"
                        >{{ perm }}</span>
                        <span
                            v-if="(role.permissions || []).length > 8"
                            class="text-xs text-gray-600"
                        >+{{ role.permissions.length - 8 }} more</span>
                    </div>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <span class="text-xs text-gray-600">{{ role.users_count }} user{{ role.users_count === 1 ? '' : 's' }}</span>
                    <Link
                        :href="`/admin/roles/${role.id}/edit`"
                        class="text-sm text-indigo-400 hover:text-indigo-300 transition"
                    >Edit</Link>
                    <button
                        v-if="!role.is_system"
                        @click="deleteRole(role)"
                        class="text-sm text-red-500 hover:text-red-400 transition"
                    >Delete</button>
                </div>
            </div>

            <div v-if="roles.length === 0" class="text-center py-16 text-gray-600">
                No roles yet. <Link href="/admin/roles/create" class="text-indigo-400 hover:text-indigo-300">Create one.</Link>
            </div>
        </div>
    </div>
</template>
