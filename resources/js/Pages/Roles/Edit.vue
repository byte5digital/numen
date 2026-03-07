<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    role:           { type: Object, default: null },
    allPermissions: { type: Object, required: true },
});

const isEdit = !!props.role;

const aiLimits = props.role?.ai_limits ?? {};

const form = useForm({
    name:                               props.role?.name ?? '',
    description:                        props.role?.description ?? '',
    permissions:                        props.role?.permissions ?? [],
    ai_daily_generations:               aiLimits.daily_generations ?? '',
    ai_daily_image_generations:         aiLimits.daily_image_generations ?? '',
    ai_monthly_cost_limit_usd:          aiLimits.monthly_cost_limit_usd ?? '',
    ai_max_tokens_per_request:          aiLimits.max_tokens_per_request ?? '',
    ai_allowed_models:                  aiLimits.allowed_models ?? [],
    ai_require_approval_above_cost_usd: aiLimits.require_approval_above_cost_usd ?? '',
});

function togglePermission(perm) {
    const idx = form.permissions.indexOf(perm);
    if (idx === -1) {
        form.permissions.push(perm);
    } else {
        form.permissions.splice(idx, 1);
    }
}

function toggleDomainWildcard(domain) {
    const wild = `${domain}.*`;
    const idx = form.permissions.indexOf(wild);
    if (idx === -1) {
        // Remove individual perms for this domain, add wildcard
        form.permissions = form.permissions.filter(p => !p.startsWith(`${domain}.`));
        form.permissions.push(wild);
    } else {
        form.permissions.splice(idx, 1);
    }
}

function hasPermission(perm) {
    return form.permissions.includes(perm) || form.permissions.includes('*');
}

function hasDomainWildcard(domain) {
    return form.permissions.includes(`${domain}.*`) || form.permissions.includes('*');
}

function submit() {
    if (isEdit) {
        form.put(`/admin/roles/${props.role.id}`);
    } else {
        form.post('/admin/roles');
    }
}
</script>

<template>
    <div class="max-w-4xl">
        <div class="mb-8">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-3">
                <Link href="/admin/roles" class="hover:text-indigo-400 transition">Roles</Link>
                <span>/</span>
                <span class="text-gray-300">{{ isEdit ? role.name : 'New Role' }}</span>
            </div>
            <h1 class="text-2xl font-bold text-white">{{ isEdit ? 'Edit Role' : 'Create Role' }}</h1>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Basic info -->
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6 space-y-5">
                <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Basic Info</h2>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        :disabled="role?.is_system"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none disabled:opacity-50"
                        placeholder="e.g. Content Manager"
                        required
                    />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-400">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Description</label>
                    <input
                        v-model="form.description"
                        type="text"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none"
                        placeholder="What can this role do?"
                    />
                </div>
            </div>

            <!-- Permissions -->
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Permissions</h2>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="form.permissions.includes('*')"
                            @change="e => { form.permissions = e.target.checked ? ['*'] : [] }"
                            class="rounded border-gray-600 bg-gray-800 text-indigo-500 focus:ring-indigo-500"
                        />
                        <span class="text-sm text-gray-300">Full Access (*)</span>
                    </label>
                </div>

                <div v-if="!form.permissions.includes('*')" class="space-y-6">
                    <div v-for="(perms, domain) in allPermissions" :key="domain">
                        <div class="flex items-center gap-2 mb-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    :checked="hasDomainWildcard(domain)"
                                    @change="() => toggleDomainWildcard(domain)"
                                    class="rounded border-gray-600 bg-gray-800 text-indigo-500 focus:ring-indigo-500"
                                />
                                <span class="text-sm font-semibold text-gray-300 capitalize">{{ domain }}</span>
                                <span class="text-xs text-gray-600 font-mono">{{ domain }}.*</span>
                            </label>
                        </div>
                        <div class="ml-6 grid grid-cols-2 sm:grid-cols-3 gap-2">
                            <label
                                v-for="perm in perms"
                                :key="perm"
                                class="flex items-center gap-2 cursor-pointer"
                                :class="{ 'opacity-40': hasDomainWildcard(domain) }"
                            >
                                <input
                                    type="checkbox"
                                    :checked="hasPermission(perm) || hasDomainWildcard(domain)"
                                    :disabled="hasDomainWildcard(domain)"
                                    @change="() => togglePermission(perm)"
                                    class="rounded border-gray-600 bg-gray-800 text-indigo-500 focus:ring-indigo-500 disabled:opacity-50"
                                />
                                <span class="text-xs text-gray-400 font-mono">{{ perm }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <p v-if="form.errors.permissions" class="mt-2 text-xs text-red-400">{{ form.errors.permissions }}</p>
            </div>

            <!-- AI Limits -->
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6 space-y-5">
                <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">AI Budget &amp; Limits</h2>
                <p class="text-xs text-gray-600">Leave blank for unlimited / no restriction.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Daily Generations</label>
                        <input
                            v-model="form.ai_daily_generations"
                            type="number" min="0"
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 outline-none"
                            placeholder="e.g. 100"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Daily Image Generations</label>
                        <input
                            v-model="form.ai_daily_image_generations"
                            type="number" min="0"
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 outline-none"
                            placeholder="e.g. 20"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Monthly Cost Limit (USD)</label>
                        <input
                            v-model="form.ai_monthly_cost_limit_usd"
                            type="number" min="0" step="0.01"
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 outline-none"
                            placeholder="e.g. 50.00"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Max Tokens / Request</label>
                        <input
                            v-model="form.ai_max_tokens_per_request"
                            type="number" min="0"
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 outline-none"
                            placeholder="e.g. 4096"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Approval Threshold (USD)</label>
                        <input
                            v-model="form.ai_require_approval_above_cost_usd"
                            type="number" min="0" step="0.01"
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 outline-none"
                            placeholder="e.g. 1.00"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        Allowed Models
                        <span class="text-xs text-gray-600 font-normal ml-1">(one per line, blank = all)</span>
                    </label>
                    <textarea
                        :value="(form.ai_allowed_models || []).join('\n')"
                        @input="e => form.ai_allowed_models = e.target.value.split('\n').map(s => s.trim()).filter(Boolean)"
                        rows="3"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none font-mono text-sm"
                        placeholder="claude-haiku-4-5&#10;claude-sonnet-4-6"
                    ></textarea>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-medium px-6 py-2.5 rounded-lg transition"
                >
                    {{ isEdit ? 'Save Changes' : 'Create Role' }}
                </button>
                <Link href="/admin/roles" class="text-sm text-gray-500 hover:text-gray-300 transition">
                    Cancel
                </Link>
            </div>
        </form>
    </div>
</template>
