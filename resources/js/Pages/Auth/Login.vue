<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex items-center justify-center px-4">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-3 mb-2">
                    <span class="text-4xl">⚡</span>
                    <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Numen</h1>
                </div>
                <p class="text-slate-400 text-sm">by byte5 — AI-First Content Management</p>
            </div>

            <!-- Login Card -->
            <form @submit.prevent="submit" class="bg-white rounded-2xl border border-slate-200 p-8 shadow-lg">
                <h2 class="text-xl font-semibold text-slate-900 mb-6">Sign in</h2>

                <!-- Email -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1.5">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="w-full bg-white border border-slate-200 rounded-lg px-4 py-2.5 text-slate-900 placeholder-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition"
                        placeholder="you@company.com"
                        required
                        autofocus
                    />
                </div>

                <!-- Password -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-600 mb-1.5">Password</label>
                    <input
                        v-model="form.password"
                        type="password"
                        class="w-full bg-white border border-slate-200 rounded-lg px-4 py-2.5 text-slate-900 placeholder-slate-300 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition"
                        placeholder="••••••••"
                        required
                    />
                </div>

                <!-- Remember + Error -->
                <div class="flex items-center justify-between mb-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="form.remember" type="checkbox" class="rounded bg-white border-slate-300 text-indigo-500 focus:ring-indigo-500" />
                        <span class="text-sm text-slate-600">Remember me</span>
                    </label>
                </div>

                <p v-if="form.errors.email" class="text-sm text-red-500 mb-4">{{ form.errors.email }}</p>

                <!-- Submit -->
                <button
                    type="submit"
                    class="w-full py-2.5 rounded-full font-medium text-white transition shadow-md"
                    :class="form.processing ? 'bg-slate-400 cursor-wait' : 'bg-indigo-500 hover:bg-indigo-600'"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Signing in...' : 'Sign in' }}
                </button>
            </form>

            <p class="text-center text-xs text-slate-400 mt-6">
                AI-First Content Management
            </p>
        </div>
    </div>
</template>

<script>
// No layout for login page
export default { layout: false };
</script>
