<script setup>
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    spaceId: { type: String, required: true },
});

const emit = defineEmits(['connected']);

const sourceUrl = ref('');
const detecting = ref(false);
const connecting = ref(false);
const detected = ref(null);
const error = ref(null);
const credentials = ref({ api_key: '' });

const cmsLogos = {
    wordpress: '🔵',
    drupal: '💧',
    joomla: '🟠',
    contentful: '🟡',
    strapi: '🟣',
    unknown: '❓',
};

async function detectCms() {
    if (!sourceUrl.value) return;
    detecting.value = true;
    error.value = null;
    detected.value = null;

    try {
        const { data } = await axios.post(`/api/v1/spaces/${props.spaceId}/migrations/detect`, {
            url: sourceUrl.value,
        });
        detected.value = data.data ?? data;
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Failed to detect CMS type';
    } finally {
        detecting.value = false;
    }
}

async function connectAndScan() {
    connecting.value = true;
    error.value = null;

    try {
        // Create session
        const { data: sessionData } = await axios.post(`/api/v1/spaces/${props.spaceId}/migrations`, {
            source_url: sourceUrl.value,
            source_type: detected.value?.cms_type ?? detected.value?.type,
            config: {
                credentials: credentials.value,
            },
        });

        const session = sessionData.data ?? sessionData;

        // Fetch schema
        const { data: schemaData } = await axios.get(
            `/api/v1/spaces/${props.spaceId}/migrations/${session.id}/schema`
        );

        emit('connected', {
            session,
            schema: schemaData.data ?? schemaData,
        });
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Failed to connect and scan';
    } finally {
        connecting.value = false;
    }
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-semibold text-white mb-1">Connect to Source CMS</h2>
            <p class="text-sm text-gray-500">Enter the URL of the CMS you want to migrate content from.</p>
        </div>

        <!-- URL Input -->
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Source URL</label>
            <div class="flex gap-3">
                <input
                    v-model="sourceUrl"
                    type="url"
                    placeholder="https://example.com"
                    class="flex-1 px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
                    @keyup.enter="detectCms"
                />
                <button
                    @click="detectCms"
                    :disabled="!sourceUrl || detecting"
                    class="px-5 py-2.5 bg-gray-700 text-white rounded-lg text-sm font-medium hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition"
                >
                    <span v-if="detecting" class="flex items-center gap-2">
                        <span class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                        Detecting…
                    </span>
                    <span v-else>Detect CMS</span>
                </button>
            </div>
        </div>

        <!-- Error -->
        <div v-if="error" class="p-4 bg-red-900/20 border border-red-800 rounded-lg">
            <p class="text-sm text-red-400">{{ error }}</p>
        </div>

        <!-- Detected CMS -->
        <div v-if="detected" class="p-5 bg-gray-800/50 border border-gray-700 rounded-lg">
            <div class="flex items-center gap-4 mb-4">
                <span class="text-3xl">{{ cmsLogos[detected.cms_type ?? detected.type] ?? cmsLogos.unknown }}</span>
                <div>
                    <h3 class="text-white font-semibold capitalize">{{ detected.cms_type ?? detected.type }}</h3>
                    <p class="text-sm text-gray-400">
                        Version: {{ detected.version ?? 'Unknown' }}
                        <span v-if="detected.confidence" class="ml-2">
                            · Confidence: <span class="text-emerald-400">{{ Math.round(detected.confidence * 100) }}%</span>
                        </span>
                    </p>
                </div>
            </div>

            <!-- Credentials -->
            <div class="space-y-3 mb-5">
                <label class="block text-sm font-medium text-gray-300">API Key / Token (optional)</label>
                <input
                    v-model="credentials.api_key"
                    type="password"
                    placeholder="Enter API key if required"
                    class="w-full px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none"
                />
            </div>

            <!-- Connect Button -->
            <button
                @click="connectAndScan"
                :disabled="connecting"
                class="w-full px-5 py-3 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center justify-center gap-2"
            >
                <span v-if="connecting" class="flex items-center gap-2">
                    <span class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                    Connecting & Scanning…
                </span>
                <span v-else>🔌 Connect & Scan</span>
            </button>
        </div>
    </div>
</template>
