<script setup>
import { ref, reactive, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    current:         { type: Object, default: () => ({}) },
    providerStatus:  { type: Object, default: () => ({}) },
    availableModels: { type: Object, default: () => ({}) },
    keySet:          { type: Object, default: () => ({}) }, // { anthropic: bool, openai: bool, azure: bool }
});

const flash   = computed(() => usePage().props.flash ?? {});
const saving  = ref(null); // 'providers' | 'models' | 'costs'

// ── Reactive form state ─────────────────────────────────────────────────────
const providers = reactive({ ...props.current });
const models    = reactive({ ...props.current });
const costs     = reactive({ ...props.current });

const activeTab = ref('providers');

// ── Provider labels & logos ──────────────────────────────────────────────────
const providerMeta = {
    anthropic: { label: 'Anthropic', color: 'indigo', logo: '🤖' },
    openai:    { label: 'OpenAI',    color: 'emerald', logo: '✦' },
    azure:     { label: 'Azure AI Foundry', color: 'blue', logo: '☁' },
};

// ── Model role labels ────────────────────────────────────────────────────────
const modelRoles = [
    { key: 'ai.models.generation',         label: 'Generation',          description: 'Primary content writing (ContentCreator agent)' },
    { key: 'ai.models.generation_premium', label: 'Generation (Premium)', description: 'Premium content for high-priority briefs' },
    { key: 'ai.models.seo',                label: 'SEO Optimization',     description: 'SEO Expert agent — optimizes metadata and keywords' },
    { key: 'ai.models.review',             label: 'Editorial Review',     description: 'Editorial Director agent — quality gating' },
    { key: 'ai.models.planning',           label: 'Planning',             description: 'Pipeline planning and content strategy' },
    { key: 'ai.models.classification',     label: 'Classification',       description: 'Fast classification tasks (type, tone, tags)' },
];

// All available model options (builtin + custom freetext)
const allModelOptions = computed(() => {
    const opts = new Set();
    Object.values(props.availableModels).flat().forEach(m => opts.add(m));
    return [...opts].sort();
});

// ── Save handlers ────────────────────────────────────────────────────────────
function saveProviders() {
    saving.value = 'providers';
    router.post('/admin/settings/providers', { ...providers }, {
        preserveScroll: true,
        onFinish: () => { saving.value = null; },
    });
}

function saveModels() {
    saving.value = 'models';
    router.post('/admin/settings/models', {
        'ai.models.generation':         models['ai.models.generation'],
        'ai.models.generation_premium': models['ai.models.generation_premium'],
        'ai.models.seo':                models['ai.models.seo'],
        'ai.models.review':             models['ai.models.review'],
        'ai.models.planning':           models['ai.models.planning'],
        'ai.models.classification':     models['ai.models.classification'],
    }, {
        preserveScroll: true,
        onFinish: () => { saving.value = null; },
    });
}

function saveCosts() {
    saving.value = 'costs';
    router.post('/admin/settings/costs', {
        'ai.cost_limits.daily_usd':       costs['ai.cost_limits.daily_usd'],
        'ai.cost_limits.per_content_usd': costs['ai.cost_limits.per_content_usd'],
        'ai.cost_limits.monthly_usd':     costs['ai.cost_limits.monthly_usd'],
    }, {
        preserveScroll: true,
        onFinish: () => { saving.value = null; },
    });
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function statusDot(providerName) {
    const s = props.providerStatus[providerName];
    if (!s?.key_set)   return { cls: 'bg-gray-600', label: 'No API key' };
    if (!s?.available) return { cls: 'bg-amber-400', label: 'Rate limited' };
    return { cls: 'bg-emerald-400 shadow-[0_0_6px_#34d399]', label: 'Available' };
}

const tabs = [
    { key: 'providers', label: 'AI Providers', icon: '🔑' },
    { key: 'models',    label: 'Model Roles',  icon: '🧠' },
    { key: 'costs',     label: 'Cost Limits',  icon: '💰' },
];
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-white">Settings</h1>
            <p class="text-gray-500 mt-1">Configure AI providers, model assignments, and cost controls</p>
        </div>

        <!-- Flash -->
        <div v-if="flash.success"
             class="mb-5 px-4 py-3 bg-emerald-500/10 border border-emerald-500/20 rounded-lg text-emerald-400 text-sm">
            {{ flash.success }}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

            <!-- Sidebar tabs -->
            <div class="space-y-1">
                <button v-for="tab in tabs" :key="tab.key"
                        @click="activeTab = tab.key"
                        class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition text-left"
                        :class="activeTab === tab.key
                            ? 'bg-indigo-600/20 text-indigo-400 border border-indigo-500/20'
                            : 'text-gray-400 hover:bg-gray-900 hover:text-white border border-transparent'">
                    <span>{{ tab.icon }}</span>
                    {{ tab.label }}
                </button>

                <!-- Live provider status summary -->
                <div class="mt-6 pt-4 border-t border-gray-800 space-y-2">
                    <p class="text-xs text-gray-600 uppercase tracking-wide px-2">Provider Status</p>
                    <div v-for="(status, name) in providerStatus" :key="name"
                         class="flex items-center gap-2 px-2 py-1.5">
                        <div class="h-2 w-2 rounded-full shrink-0" :class="statusDot(name).cls" />
                        <span class="text-xs text-gray-400">{{ providerMeta[name]?.label ?? name }}</span>
                        <span class="ml-auto text-xs" :class="status.key_set ? 'text-gray-500' : 'text-gray-700'">
                            {{ statusDot(name).label }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Content area -->
            <div class="lg:col-span-3">

                <!-- ═══ AI PROVIDERS TAB ════════════════════════════════════ -->
                <div v-if="activeTab === 'providers'" class="space-y-6">

                    <!-- Routing -->
                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                        <h2 class="text-base font-semibold text-white mb-4">Routing & Fallback</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1.5">Default Provider</label>
                                <select v-model="providers['ai.default_provider']"
                                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                    <option value="anthropic">Anthropic</option>
                                    <option value="openai">OpenAI</option>
                                    <option value="azure">Azure AI Foundry</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1.5">Fallback Chain
                                    <span class="text-gray-600">(comma-separated, in order)</span>
                                </label>
                                <input v-model="providers['ai.fallback_chain']"
                                       placeholder="anthropic,openai,azure"
                                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white font-mono focus:outline-none focus:border-indigo-500" />
                                <p class="text-xs text-gray-600 mt-1">On 429 or 5xx the next provider is tried automatically.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Provider cards -->
                    <div v-for="(meta, providerName) in providerMeta" :key="providerName"
                         class="bg-gray-900 rounded-xl border p-6"
                         :class="providerStatus[providerName]?.key_set
                             ? 'border-gray-700'
                             : 'border-gray-800'">

                        <!-- Provider header -->
                        <div class="flex items-center gap-3 mb-5">
                            <div class="h-2.5 w-2.5 rounded-full shrink-0" :class="statusDot(providerName).cls" />
                            <h3 class="text-base font-semibold text-white">{{ meta.label }}</h3>
                            <span class="text-xs px-2 py-0.5 rounded-full border"
                                  :class="providerStatus[providerName]?.key_set
                                      ? 'border-emerald-500/20 text-emerald-400 bg-emerald-500/5'
                                      : 'border-gray-700 text-gray-600 bg-gray-900'">
                                {{ statusDot(providerName).label }}
                            </span>
                            <span v-if="providers['ai.default_provider'] === providerName"
                                  class="ml-auto text-xs px-2 py-0.5 bg-indigo-500/20 text-indigo-300 rounded-full border border-indigo-500/20">
                                default
                            </span>
                        </div>

                        <!-- Anthropic fields -->
                        <template v-if="providerName === 'anthropic'">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label class="text-xs text-gray-400">API Key</label>
                                        <span v-if="keySet.anthropic"
                                              class="text-xs px-2 py-0.5 bg-emerald-500/10 text-emerald-400 rounded border border-emerald-500/20">
                                            Key configured ✓
                                        </span>
                                    </div>
                                    <input v-model="providers['ai.providers.anthropic.api_key']"
                                           type="password" autocomplete="new-password"
                                           :placeholder="keySet.anthropic ? 'Enter new key to replace…' : 'sk-ant-...'"
                                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white font-mono focus:outline-none focus:border-indigo-500" />
                                    <p class="text-xs text-gray-600 mt-1">Leave blank to keep the existing key.</p>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1.5">Base URL</label>
                                    <input v-model="providers['ai.providers.anthropic.base_url']"
                                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white font-mono focus:outline-none focus:border-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1.5">Default Model</label>
                                    <div class="flex gap-2">
                                        <select v-model="providers['ai.providers.anthropic.default_model']"
                                                class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                            <option v-for="m in availableModels.anthropic" :key="m" :value="m">{{ m }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- OpenAI fields -->
                        <template v-else-if="providerName === 'openai'">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label class="text-xs text-gray-400">API Key</label>
                                        <span v-if="keySet.openai"
                                              class="text-xs px-2 py-0.5 bg-emerald-500/10 text-emerald-400 rounded border border-emerald-500/20">
                                            Key configured ✓
                                        </span>
                                    </div>
                                    <input v-model="providers['ai.providers.openai.api_key']"
                                           type="password" autocomplete="new-password"
                                           :placeholder="keySet.openai ? 'Enter new key to replace…' : 'sk-proj-...'"
                                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white font-mono focus:outline-none focus:border-indigo-500" />
                                    <p class="text-xs text-gray-600 mt-1">Leave blank to keep the existing key.</p>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1.5">Base URL</label>
                                    <input v-model="providers['ai.providers.openai.base_url']"
                                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white font-mono focus:outline-none focus:border-indigo-500" />
                                    <p class="text-xs text-gray-600 mt-1">Change for OpenAI-compatible APIs (LM Studio, Ollama, etc.)</p>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1.5">Default Model</label>
                                    <select v-model="providers['ai.providers.openai.default_model']"
                                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                        <option v-for="m in availableModels.openai" :key="m" :value="m">{{ m }}</option>
                                    </select>
                                </div>
                            </div>
                        </template>

                        <!-- Azure fields -->
                        <template v-else-if="providerName === 'azure'">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <label class="text-xs text-gray-400">API Key</label>
                                        <span v-if="keySet.azure"
                                              class="text-xs px-2 py-0.5 bg-emerald-500/10 text-emerald-400 rounded border border-emerald-500/20">
                                            Key configured ✓
                                        </span>
                                    </div>
                                    <input v-model="providers['ai.providers.azure.api_key']"
                                           type="password" autocomplete="new-password"
                                           :placeholder="keySet.azure ? 'Enter new key to replace…' : 'Azure OpenAI key'"
                                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white font-mono focus:outline-none focus:border-indigo-500" />
                                    <p class="text-xs text-gray-600 mt-1">Leave blank to keep the existing key.</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs text-gray-400 mb-1.5">Endpoint</label>
                                    <input v-model="providers['ai.providers.azure.endpoint']"
                                           placeholder="https://YOUR-RESOURCE.openai.azure.com"
                                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white font-mono focus:outline-none focus:border-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1.5">API Version</label>
                                    <input v-model="providers['ai.providers.azure.api_version']"
                                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white font-mono focus:outline-none focus:border-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1.5">Default Model</label>
                                    <select v-model="providers['ai.providers.azure.default_model']"
                                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                        <option v-for="m in availableModels.azure" :key="m" :value="m">{{ m }}</option>
                                    </select>
                                </div>
                                <!-- Deployment names -->
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1.5">Deployment: gpt-4o</label>
                                    <input v-model="providers['ai.providers.azure.deployments.gpt-4o']"
                                           placeholder="gpt-4o"
                                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white font-mono focus:outline-none focus:border-indigo-500" />
                                    <p class="text-xs text-gray-600 mt-1">Azure deployment name for this model</p>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1.5">Deployment: gpt-4o-mini</label>
                                    <input v-model="providers['ai.providers.azure.deployments.gpt-4o-mini']"
                                           placeholder="gpt-4o-mini"
                                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white font-mono focus:outline-none focus:border-indigo-500" />
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Save -->
                    <div class="flex justify-end">
                        <button @click="saveProviders" :disabled="saving === 'providers'"
                                class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition">
                            {{ saving === 'providers' ? 'Saving…' : 'Save Provider Settings' }}
                        </button>
                    </div>
                </div>

                <!-- ═══ MODEL ROLES TAB ════════════════════════════════════ -->
                <div v-else-if="activeTab === 'models'" class="space-y-4">
                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                        <h2 class="text-base font-semibold text-white mb-1">Model Role Assignments</h2>
                        <p class="text-sm text-gray-500 mb-6">
                            Map each pipeline stage to a specific model.
                            Use <code class="text-indigo-400 bg-indigo-500/10 px-1 rounded">provider:model</code>
                            format to route a role to a specific provider
                            (e.g. <code class="text-indigo-400 bg-indigo-500/10 px-1 rounded">openai:gpt-4o</code>).
                        </p>

                        <div class="space-y-5">
                            <div v-for="role in modelRoles" :key="role.key">
                                <label class="block text-sm font-medium text-white mb-1">{{ role.label }}</label>
                                <p class="text-xs text-gray-500 mb-2">{{ role.description }}</p>
                                <div class="flex gap-3">
                                    <!-- Quick-select from known models -->
                                    <select v-model="models[role.key]"
                                            class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                        <optgroup label="Anthropic">
                                            <option v-for="m in availableModels.anthropic" :key="'a:'+m" :value="m">{{ m }}</option>
                                        </optgroup>
                                        <optgroup label="OpenAI">
                                            <option v-for="m in availableModels.openai" :key="'o:'+m" :value="'openai:'+m">openai:{{ m }}</option>
                                        </optgroup>
                                        <optgroup label="Azure AI Foundry">
                                            <option v-for="m in availableModels.azure" :key="'az:'+m" :value="'azure:'+m">azure:{{ m }}</option>
                                        </optgroup>
                                    </select>
                                    <!-- Or freetext for custom models -->
                                    <input v-model="models[role.key]"
                                           placeholder="or type custom model…"
                                           class="w-48 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white font-mono focus:outline-none focus:border-indigo-500" />
                                </div>
                                <p class="text-xs text-gray-600 mt-1 font-mono">
                                    Current: {{ models[role.key] }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button @click="saveModels" :disabled="saving === 'models'"
                                class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition">
                            {{ saving === 'models' ? 'Saving…' : 'Save Model Assignments' }}
                        </button>
                    </div>
                </div>

                <!-- ═══ COST LIMITS TAB ════════════════════════════════════ -->
                <div v-else-if="activeTab === 'costs'" class="space-y-4">
                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                        <h2 class="text-base font-semibold text-white mb-1">Cost Controls</h2>
                        <p class="text-sm text-gray-500 mb-6">
                            Hard limits in USD. The pipeline will pause and alert when a limit is reached.
                            Costs are tracked across all providers.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1.5">Daily Limit (USD)</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500 text-sm">$</span>
                                    <input v-model="costs['ai.cost_limits.daily_usd']"
                                           type="number" min="0" step="1"
                                           class="w-full bg-gray-800 border border-gray-700 rounded-lg pl-7 pr-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" />
                                </div>
                                <p class="text-xs text-gray-600 mt-1">Resets at midnight UTC</p>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1.5">Per-Content Limit (USD)</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500 text-sm">$</span>
                                    <input v-model="costs['ai.cost_limits.per_content_usd']"
                                           type="number" min="0" step="0.1"
                                           class="w-full bg-gray-800 border border-gray-700 rounded-lg pl-7 pr-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" />
                                </div>
                                <p class="text-xs text-gray-600 mt-1">Max spend per single content generation</p>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1.5">Monthly Limit (USD)</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500 text-sm">$</span>
                                    <input v-model="costs['ai.cost_limits.monthly_usd']"
                                           type="number" min="0" step="10"
                                           class="w-full bg-gray-800 border border-gray-700 rounded-lg pl-7 pr-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" />
                                </div>
                            </div>
                        </div>

                        <!-- Pricing reference table -->
                        <div class="mt-8 pt-6 border-t border-gray-800">
                            <h3 class="text-sm font-medium text-gray-400 mb-3">Pricing Reference (per million tokens)</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="text-gray-600 border-b border-gray-800">
                                            <th class="text-left py-2 pr-4 font-medium">Model</th>
                                            <th class="text-right py-2 px-3 font-medium">Input</th>
                                            <th class="text-right py-2 px-3 font-medium">Output</th>
                                            <th class="text-right py-2 pl-3 font-medium">Cache</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-gray-400">
                                        <tr v-for="row in pricingTable" :key="row.model"
                                            class="border-b border-gray-800/50">
                                            <td class="py-2 pr-4 font-mono text-gray-300">{{ row.model }}</td>
                                            <td class="text-right py-2 px-3">${{ row.input }}</td>
                                            <td class="text-right py-2 px-3">${{ row.output }}</td>
                                            <td class="text-right py-2 pl-3">${{ row.cache }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button @click="saveCosts" :disabled="saving === 'costs'"
                                class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition">
                            {{ saving === 'costs' ? 'Saving…' : 'Save Cost Limits' }}
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script>
import MainLayout from '../../Layouts/MainLayout.vue';
export default {
    layout: MainLayout,
    data() {
        return {
            pricingTable: [
                { model: 'claude-opus-4-6',    input: '15.00', output: '75.00',  cache: '1.50' },
                { model: 'claude-sonnet-4-6',  input: '3.00',  output: '15.00',  cache: '0.30' },
                { model: 'claude-haiku-4-5',   input: '0.80',  output: '4.00',   cache: '0.08' },
                { model: 'gpt-4.1',            input: '2.00',  output: '8.00',   cache: '0.50' },
                { model: 'gpt-4.1-mini',       input: '0.40',  output: '1.60',   cache: '0.10' },
                { model: 'gpt-4.1-nano',       input: '0.10',  output: '0.40',   cache: '0.03' },
                { model: 'gpt-4.5-preview',    input: '75.00', output: '150.00', cache: '37.50' },
                { model: 'gpt-4o',             input: '2.50',  output: '10.00',  cache: '1.25' },
                { model: 'gpt-4o-mini',        input: '0.15',  output: '0.60',   cache: '0.08' },
                { model: 'o4-mini',            input: '1.10',  output: '4.40',   cache: '0.28' },
                { model: 'o3',                 input: '10.00', output: '40.00',  cache: '2.50' },
                { model: 'o3-mini',            input: '1.10',  output: '4.40',   cache: '0.55' },
                { model: 'o1',                 input: '15.00', output: '60.00',  cache: '7.50' },
            ],
        };
    },
};
</script>
