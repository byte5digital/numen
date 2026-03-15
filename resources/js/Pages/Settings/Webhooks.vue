<script setup>
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    webhooks: { type: Array, default: () => [] },
    newSecret: { type: String, default: null },
});

const flash = computed(() => usePage().props.flash ?? {});
const copied = ref(false);
const expandedWebhookId = ref(null);
const deliveries = ref({});
const loadingDeliveries = ref({});
const editingId = ref(null);

const form = useForm({
    url: '',
    events: [],
    is_active: true,
});

const availableEvents = [
    {
        domain: 'content',
        events: [
            { value: 'content.created', label: 'Created' },
            { value: 'content.updated', label: 'Updated' },
            { value: 'content.published', label: 'Published' },
            { value: 'content.deleted', label: 'Deleted' },
        ],
    },
    {
        domain: 'pipeline',
        events: [
            { value: 'pipeline.started', label: 'Started' },
            { value: 'pipeline.completed', label: 'Completed' },
            { value: 'pipeline.failed', label: 'Failed' },
        ],
    },
    {
        domain: 'media',
        events: [
            { value: 'media.uploaded', label: 'Uploaded' },
            { value: 'media.deleted', label: 'Deleted' },
        ],
    },
    {
        domain: 'user',
        events: [
            { value: 'user.invited', label: 'Invited' },
            { value: 'user.removed', label: 'Removed' },
        ],
    },
];

function toggleEvent(eventValue) {
    const index = form.events.indexOf(eventValue);
    if (index > -1) {
        form.events.splice(index, 1);
    } else {
        form.events.push(eventValue);
    }
}

function selectWildcard() {
    form.events = ['*'];
}

function create() {
    form.post('/admin/webhooks', { preserveScroll: true });
}

function startEdit(webhook) {
    editingId.value = webhook.id;
    form.url = webhook.url;
    form.events = [...webhook.events];
    form.is_active = webhook.is_active;
}

function cancelEdit() {
    editingId.value = null;
    form.reset();
}

function saveEdit() {
    form.put(`/admin/webhooks/${editingId.value}`, { preserveScroll: true });
    editingId.value = null;
}

function deleteWebhook(id) {
    if (!confirm('Delete this webhook? This action cannot be undone.')) return;
    router.delete(`/admin/webhooks/${id}`, { preserveScroll: true });
}

function rotateSecret(id) {
    if (!confirm('Generate a new secret? Requests with the old secret will be rejected.')) return;
    router.post(`/admin/webhooks/${id}/rotate-secret`, {}, { preserveScroll: true });
}

function toggleDeliveries(webhookId) {
    if (expandedWebhookId.value === webhookId) {
        expandedWebhookId.value = null;
    } else {
        expandedWebhookId.value = webhookId;
        fetchDeliveries(webhookId);
    }
}

async function fetchDeliveries(webhookId) {
    if (deliveries.value[webhookId]) return;
    loadingDeliveries.value[webhookId] = true;
    try {
        const response = await fetch(`/admin/webhooks/${webhookId}/deliveries`);
        const json = await response.json();
        deliveries.value[webhookId] = json.data || [];
    } catch (error) {
        console.error('Failed to fetch deliveries:', error);
    } finally {
        loadingDeliveries.value[webhookId] = false;
    }
}

function copySecret() {
    navigator.clipboard.writeText(props.newSecret);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function redeliver(webhookId, deliveryId) {
    if (!confirm('Re-queue this delivery?')) return;
    const token = document.querySelector('meta[name="csrf-token"]').content;
    fetch(`/admin/webhooks/${webhookId}/deliveries/${deliveryId}/redeliver`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
    })
        .then(r => r.json())
        .then(() => fetchDeliveries(webhookId))
        .catch(e => console.error('Redeliver failed:', e));
}

function truncateUrl(url) {
    if (url.length > 60) return url.substring(0, 57) + '…';
    return url;
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

function statusColor(status) {
    switch (status) {
        case 'pending': return 'bg-yellow-950 text-yellow-400 border border-yellow-700';
        case 'delivered': return 'bg-emerald-950 text-emerald-400 border border-emerald-700';
        case 'failed': return 'bg-red-950 text-red-400 border border-red-700';
        case 'abandoned': return 'bg-gray-800 text-gray-400 border border-gray-700';
        default: return 'bg-gray-800 text-gray-400 border border-gray-700';
    }
}
</script>

<template>
    <Head title="Webhooks" />

    <div class="max-w-4xl mx-auto space-y-8">
        <div>
            <h1 class="text-2xl font-bold text-white">Webhooks</h1>
            <p class="text-gray-400 mt-1 text-sm">Send real-time HTTP notifications when events occur in your space.</p>
        </div>

        <div v-if="newSecret" class="bg-emerald-950 border border-emerald-700 rounded-xl p-5 space-y-3">
            <div class="flex items-center gap-2 text-emerald-400 font-semibold">
                <span>✅</span> Signing secret — copy it now, it won't be shown again.
            </div>
            <div class="flex items-center gap-3">
                <code class="flex-1 text-sm text-emerald-300 bg-emerald-900/40 px-4 py-2.5 rounded-lg font-mono break-all select-all">{{ newSecret }}</code>
                <button @click="copySecret" class="shrink-0 px-4 py-2.5 rounded-lg bg-emerald-700 hover:bg-emerald-600 text-white text-sm font-medium">
                    {{ copied ? 'Copied!' : 'Copy' }}
                </button>
            </div>
        </div>

        <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
            <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wider mb-5">{{ editingId === null ? 'Create webhook' : 'Edit webhook' }}</h2>

            <form @submit.prevent="editingId === null ? create() : saveEdit()" class="space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Webhook URL</label>
                    <input v-model="form.url" type="url" placeholder="https://example.com/webhooks" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500" :disabled="form.processing" required maxlength="2048" />
                    <p v-if="form.errors.url" class="text-red-400 text-xs mt-1">{{ form.errors.url }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-3">Events to subscribe</label>
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" :checked="form.events.includes('*')" @change="selectWildcard" class="w-4 h-4 rounded bg-gray-800 border-gray-700 text-indigo-600" />
                            <label class="text-sm text-gray-300 font-medium">All events</label>
                        </div>

                        <div v-for="domain in availableEvents" :key="domain.domain" class="space-y-2">
                            <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">{{ domain.domain }}.*</p>
                            <div class="pl-4 space-y-2">
                                <div v-for="event in domain.events" :key="event.value" class="flex items-center gap-2">
                                    <input type="checkbox" :checked="form.events.includes(event.value)" @change="toggleEvent(event.value)" :disabled="form.events.includes('*')" class="w-4 h-4 rounded bg-gray-800 border-gray-700 text-indigo-600 disabled:opacity-50" />
                                    <label class="text-sm text-gray-300">{{ event.label }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-if="form.errors.events" class="text-red-400 text-xs mt-2">{{ form.errors.events }}</p>
                </div>

                <div class="flex items-center gap-3">
                    <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded bg-gray-800 border-gray-700 text-indigo-600" :disabled="form.processing" />
                    <label class="text-sm font-medium text-gray-300">Active</label>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" :disabled="form.processing || !form.url.trim() || form.events.length === 0" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-semibold rounded-lg">
                        {{ editingId === null ? 'Create' : 'Save' }}
                    </button>
                    <button v-if="editingId !== null" @click="cancelEdit" type="button" class="px-5 py-2.5 bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm font-semibold rounded-lg">Cancel</button>
                </div>
            </form>
        </div>

        <div class="space-y-3">
            <div v-if="webhooks.length === 0" class="bg-gray-900 border border-gray-800 rounded-xl p-10 text-center text-gray-500 text-sm">
                No webhooks yet. Create one above.
            </div>

            <div v-for="webhook in webhooks" :key="webhook.id" class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-800/40 cursor-pointer" @click="toggleDeliveries(webhook.id)">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <p class="text-sm font-mono text-indigo-400 truncate">{{ truncateUrl(webhook.url) }}</p>
                            <span :class="['px-2 py-0.5 rounded text-xs font-medium border', webhook.is_active ? 'bg-emerald-950 text-emerald-400 border-emerald-700' : 'bg-gray-800 text-gray-400 border-gray-700']">
                                {{ webhook.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="flex gap-2 flex-wrap mb-2">
                            <span v-for="event in webhook.events" :key="event" class="px-2 py-1 bg-gray-800 text-gray-300 text-xs rounded border border-gray-700">{{ event }}</span>
                        </div>
                        <p class="text-xs text-gray-500">Created {{ formatDate(webhook.created_at) }}</p>
                    </div>

                    <div class="ml-4 shrink-0 flex gap-2">
                        <button @click.stop="startEdit(webhook)" class="px-3 py-1.5 text-xs font-medium text-indigo-400 hover:bg-indigo-500/20 rounded border border-indigo-500/30">Edit</button>
                        <button @click.stop="rotateSecret(webhook.id)" class="px-3 py-1.5 text-xs font-medium text-amber-400 hover:bg-amber-500/20 rounded border border-amber-500/30">Rotate</button>
                        <button @click.stop="deleteWebhook(webhook.id)" class="px-3 py-1.5 text-xs font-medium text-red-400 hover:bg-red-500/20 rounded border border-red-500/30">Delete</button>
                    </div>
                </div>

                <div v-show="expandedWebhookId === webhook.id" class="border-t border-gray-800 bg-gray-800/40">
                    <div class="px-6 py-4">
                        <h3 class="text-sm font-semibold text-gray-300 mb-4">Recent deliveries</h3>

                        <div v-if="loadingDeliveries[webhook.id]" class="text-center py-4 text-gray-500 text-sm">Loading...</div>
                        <div v-else-if="!deliveries[webhook.id] || deliveries[webhook.id].length === 0" class="text-center py-4 text-gray-500 text-sm">No deliveries yet</div>

                        <table v-else class="w-full text-sm">
                            <thead class="text-gray-400 text-xs uppercase border-b border-gray-700">
                                <tr>
                                    <th class="text-left px-3 py-2">Event</th>
                                    <th class="text-left px-3 py-2">Status</th>
                                    <th class="text-left px-3 py-2">HTTP</th>
                                    <th class="text-left px-3 py-2">Attempt</th>
                                    <th class="text-left px-3 py-2">Time</th>
                                    <th class="text-right px-3 py-2">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <tr v-for="delivery in deliveries[webhook.id]" :key="delivery.id" class="hover:bg-gray-700/40">
                                    <td class="px-3 py-2 text-gray-300 font-mono text-xs">{{ delivery.event_type }}</td>
                                    <td class="px-3 py-2"><span :class="['px-2 py-0.5 rounded text-xs font-medium border', statusColor(delivery.status)]">{{ delivery.status }}</span></td>
                                    <td class="px-3 py-2 text-gray-400 text-xs">{{ delivery.http_status || '—' }}</td>
                                    <td class="px-3 py-2 text-gray-400 text-xs">{{ delivery.attempt_number }}</td>
                                    <td class="px-3 py-2 text-gray-400 text-xs">{{ formatDate(delivery.created_at) }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <button v-if="['failed', 'abandoned'].includes(delivery.status)" @click="redeliver(webhook.id, delivery.id)" class="text-xs px-2 py-1 text-indigo-400 hover:bg-indigo-500/20 rounded border border-indigo-500/30">Retry</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import MainLayout from '../../Layouts/MainLayout.vue';
export default { layout: MainLayout };
</script>
