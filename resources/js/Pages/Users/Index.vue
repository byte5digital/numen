<script setup>
import { Link, router } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user);

const flash = computed(() => page.props.flash);

function deleteUser(id) {
    if (confirm('Delete this user? This action cannot be undone.')) {
        router.delete(`/admin/users/${id}`);
    }
}

const roleColors = {
    admin:  'bg-indigo-900/50 text-indigo-400',
    editor: 'bg-emerald-900/50 text-emerald-400',
    viewer: 'bg-gray-800 text-gray-400',
};
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-white">Users</h1>
                <p class="text-gray-500 mt-1">Manage admin area users and their roles</p>
            </div>
            <Link
                href="/admin/users/create"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-500 transition"
            >
                + New User
            </Link>
        </div>

        <!-- Flash messages -->
        <div v-if="flash?.success" class="mb-6 px-4 py-3 bg-emerald-900/40 border border-emerald-700 text-emerald-300 rounded-lg text-sm">
            {{ flash.success }}
        </div>
        <div v-if="flash?.error" class="mb-6 px-4 py-3 bg-red-900/40 border border-red-700 text-red-300 rounded-lg text-sm">
            {{ flash.error }}
        </div>

        <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
            <table class="w-full">
                <thead class="border-b border-gray-800">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Role</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Created</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    <tr v-for="user in users" :key="user.id" class="hover:bg-gray-800/50">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-200">{{ user.name }}</span>
                                <span v-if="user.id === authUser?.id" class="text-xs px-1.5 py-0.5 bg-indigo-500/20 text-indigo-300 rounded">you</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-400">{{ user.email }}</td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs rounded-full" :class="roleColors[user.role] ?? 'bg-gray-800 text-gray-400'">
                                {{ user.role }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-500">{{ user.created_at }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <Link
                                    :href="`/admin/users/${user.id}/edit`"
                                    class="text-xs text-gray-400 hover:text-indigo-400 transition"
                                >
                                    Edit
                                </Link>
                                <button
                                    @click="deleteUser(user.id)"
                                    class="text-xs text-gray-600 hover:text-red-400 transition"
                                    :disabled="user.id === authUser?.id"
                                    :class="{ 'opacity-30 cursor-not-allowed': user.id === authUser?.id }"
                                >
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!users.length" class="px-6 py-12 text-center">
                <p class="text-gray-600">No users found.</p>
                <Link href="/admin/users/create" class="mt-3 inline-block text-sm text-indigo-400 hover:text-indigo-300">
                    Create your first user →
                </Link>
            </div>
        </div>
    </div>
</template>
