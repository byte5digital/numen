<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user:  { type: Object, required: true },
    roles: { type: Array, default: () => [] },
});

const form = useForm({
    name:     props.user.name,
    email:    props.user.email,
    role_ids: props.user.role_ids ?? [],
    password: '',
});

function toggleRole(roleId) {
    const idx = form.role_ids.indexOf(roleId);
    if (idx === -1) {
        form.role_ids.push(roleId);
    } else {
        form.role_ids.splice(idx, 1);
    }
}

function submit() {
    form.put(`/admin/users/${props.user.id}`);
}
</script>

<template>
    <div class="max-w-2xl">
        <div class="mb-8">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-3">
                <Link href="/admin/users" class="hover:text-indigo-400 transition">Users</Link>
                <span>/</span>
                <span class="text-gray-300">Edit User</span>
            </div>
            <h1 class="text-2xl font-bold text-white">Edit User</h1>
            <p class="text-gray-500 mt-1">Update user details for <span class="text-gray-300">{{ user.name }}</span></p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6 space-y-5">
                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Name <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                        placeholder="Full name"
                        required
                    />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-400">{{ form.errors.name }}</p>
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Email <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                        placeholder="user@example.com"
                        required
                    />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-400">{{ form.errors.email }}</p>
                </div>

                <!-- Roles -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Roles</label>
                    <div class="space-y-2">
                        <label
                            v-for="role in roles"
                            :key="role.id"
                            class="flex items-center gap-3 cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                :value="role.id"
                                :checked="form.role_ids.includes(role.id)"
                                @change="() => toggleRole(role.id)"
                                class="rounded border-gray-600 bg-gray-800 text-indigo-500 focus:ring-indigo-500"
                            />
                            <span class="text-sm text-gray-300">{{ role.name }}</span>
                            <span v-if="role.description" class="text-xs text-gray-600">— {{ role.description }}</span>
                        </label>
                        <p v-if="roles.length === 0" class="text-sm text-gray-600">
                            No roles available. <Link href="/admin/roles/create" class="text-indigo-400 hover:text-indigo-300">Create one.</Link>
                        </p>
                    </div>
                    <p v-if="form.errors.role_ids" class="mt-1 text-xs text-red-400">{{ form.errors.role_ids }}</p>
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">New Password <span class="text-gray-600 font-normal">(leave blank to keep current)</span></label>
                    <input
                        v-model="form.password"
                        type="password"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                        placeholder="Minimum 8 characters"
                        autocomplete="new-password"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-xs text-red-400">{{ form.errors.password }}</p>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-500 transition disabled:opacity-50"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </button>
                <Link href="/admin/users" class="text-sm text-gray-500 hover:text-gray-300 transition">
                    Cancel
                </Link>
            </div>
        </form>
    </div>
</template>
