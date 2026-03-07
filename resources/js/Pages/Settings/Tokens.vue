<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    tokens: { type: Array, default: () => [] },
    newToken: { type: String, default: null },
});

const form = useForm({ name: '' });
const copied = ref(false);

function create() {
    form.post('/admin/tokens', { preserveScroll: true });
}

function revoke(id) {
    if (!confirm('Revoke this token? Any app using it will lose access immediately.')) return;
    router.delete(`/admin/tokens/${id}`, { preserveScroll: true });
}

function copyToken() {
    navigator.clipboard.writeText(props.newToken);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}
</script>

<template>
    <Head title="API Tokens" />

    <div class="max-w-3xl mx-auto space-y-8">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold text-white">API Tokens</h1>
            <p class="text-gray-400 mt-1 text-sm">
                Tokens authenticate write requests to the Numen API. Read endpoints remain public.
                Use <code class="text-indigo-400 bg-gray-800 px-1 rounded">Authorization: Bearer &lt;token&gt;</code> in your requests.
            </p>
        </div>

        <!-- New token banner (shown once after creation) -->
        <div v-if="newToken" class="bg-emerald-950 border border-emerald-700 rounded-xl p-5 space-y-3">
            <div class="flex items-center gap-2 text-emerald-400 font-semibold">
                <span>✅</span> Token created — copy it now, it won't be shown again.
            </div>
            <div class="flex items-center gap-3">
                <code class="flex-1 text-sm text-emerald-300 bg-emerald-900/40 px-4 py-2.5 rounded-lg font-mono break-all select-all">
                    {{ newToken }}
                </code>
                <button
                    @click="copyToken"
                    class="shrink-0 px-4 py-2.5 rounded-lg bg-emerald-700 hover:bg-emerald-600 text-white text-sm font-medium transition"
                >
                    {{ copied ? 'Copied!' : 'Copy' }}
                </button>
            </div>
        </div>

        <!-- Create form -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
            <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wider mb-4">Create new token</h2>
            <form @submit.prevent="create" class="flex gap-3">
                <input
                    v-model="form.name"
                    type="text"
                    placeholder="Token name (e.g. my-frontend)"
                    class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    :disabled="form.processing"
                    required
                    maxlength="100"
                />
                <button
                    type="submit"
                    :disabled="form.processing || !form.name.trim()"
                    class="px-5 py-2.5 bg-indigo-500 hover:bg-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-lg transition"
                >
                    Generate
                </button>
            </form>
            <p v-if="form.errors.name" class="text-red-400 text-xs mt-2">{{ form.errors.name }}</p>
        </div>

        <!-- Token list -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-800">
                <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Active tokens</h2>
            </div>

            <div v-if="tokens.length === 0" class="px-6 py-10 text-center text-gray-500 text-sm">
                No tokens yet. Generate one above.
            </div>

            <ul v-else class="divide-y divide-gray-800">
                <li v-for="token in tokens" :key="token.id" class="flex items-center justify-between px-6 py-4 hover:bg-gray-800/40 transition">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-white truncate">{{ token.name }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Created {{ formatDate(token.created_at) }}
                            <span v-if="token.last_used_at"> · Last used {{ formatDate(token.last_used_at) }}</span>
                            <span v-else> · Never used</span>
                        </p>
                    </div>
                    <button
                        @click="revoke(token.id)"
                        class="ml-4 shrink-0 px-3 py-1.5 text-xs font-medium text-red-400 hover:text-white hover:bg-red-500/20 rounded-lg border border-red-500/30 hover:border-red-500/60 transition"
                    >
                        Revoke
                    </button>
                </li>
            </ul>
        </div>

        <!-- Usage hint -->
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 space-y-3">
            <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Usage example</h2>
            <pre class="text-sm text-gray-300 bg-gray-800 rounded-lg p-4 overflow-x-auto font-mono leading-relaxed"><span class="text-gray-500"># Create a brief via the API</span>
curl -X POST {{ $page.props.ziggy?.url ?? '' }}/api/v1/briefs \
  -H <span class="text-emerald-400">"Authorization: Bearer &lt;your-token&gt;"</span> \
  -H "Content-Type: application/json" \
  -d '{"title":"My article","topic":"...","persona_id":"..."}'</pre>
            <p class="text-xs text-gray-500">
                Read endpoints (<code class="text-gray-400">/api/v1/content</code>, <code class="text-gray-400">/api/v1/pages</code>, etc.) are public — no token needed.
            </p>
        </div>
    </div>
</template>

<script>
import MainLayout from '../../Layouts/MainLayout.vue';
export default { layout: MainLayout };
</script>
