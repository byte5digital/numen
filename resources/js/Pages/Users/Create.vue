<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name:     '',
    email:    '',
    role:     'editor',
    password: '',
});

function submit() {
    form.post('/admin/users');
}
</script>

<template>
    <div class="max-w-2xl">
        <div class="mb-8">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-3">
                <Link href="/admin/users" class="hover:text-indigo-400 transition">Users</Link>
                <span>/</span>
                <span class="text-gray-300">New User</span>
            </div>
            <h1 class="text-2xl font-bold text-white">Create User</h1>
            <p class="text-gray-500 mt-1">Add a new user to the admin area</p>
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
                        autocomplete="off"
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
                        autocomplete="off"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-400">{{ form.errors.email }}</p>
                </div>

                <!-- Role -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Role <span class="text-red-500">*</span></label>
                    <select
                        v-model="form.role"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                    >
                        <option value="admin">Admin — Full access</option>
                        <option value="editor">Editor — Create & edit content</option>
                        <option value="viewer">Viewer — Read-only</option>
                    </select>
                    <p v-if="form.errors.role" class="mt-1 text-xs text-red-400">{{ form.errors.role }}</p>
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Password <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.password"
                        type="password"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                        placeholder="Minimum 8 characters"
                        required
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
                    {{ form.processing ? 'Creating...' : 'Create User' }}
                </button>
                <Link href="/admin/users" class="text-sm text-gray-500 hover:text-gray-300 transition">
                    Cancel
                </Link>
            </div>
        </form>
    </div>
</template>
