<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const flash = computed(() => page.props.flash);

const form = useForm({
    current_password:      '',
    new_password:          '',
    new_password_confirmation: '',
});

function submit() {
    form.put('/admin/profile/password', {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <div class="max-w-2xl">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-white">Change Password</h1>
            <p class="text-gray-500 mt-1">Update your account password</p>
        </div>

        <!-- Flash -->
        <div v-if="flash?.success" class="mb-6 px-4 py-3 bg-emerald-900/40 border border-emerald-700 text-emerald-300 rounded-lg text-sm">
            {{ flash.success }}
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6 space-y-5">
                <!-- Current Password -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Current Password <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.current_password"
                        type="password"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                        placeholder="Your current password"
                        required
                        autocomplete="current-password"
                    />
                    <p v-if="form.errors.current_password" class="mt-1 text-xs text-red-400">{{ form.errors.current_password }}</p>
                </div>

                <!-- New Password -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">New Password <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.new_password"
                        type="password"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                        placeholder="Minimum 8 characters"
                        required
                        autocomplete="new-password"
                    />
                    <p v-if="form.errors.new_password" class="mt-1 text-xs text-red-400">{{ form.errors.new_password }}</p>
                </div>

                <!-- Confirm New Password -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Confirm New Password <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.new_password_confirmation"
                        type="password"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                        placeholder="Repeat new password"
                        required
                        autocomplete="new-password"
                    />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-500 transition disabled:opacity-50"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Updating...' : 'Update Password' }}
                </button>
            </div>
        </form>
    </div>
</template>
