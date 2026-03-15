<script setup>
import { ref } from "vue";
import { Head } from "@inertiajs/vue3";
import MainLayout from "@/Layouts/MainLayout.vue";
import axios from "axios";

const props = defineProps({
    plugins: { type: Array, default: () => [] },
});

const plugins = ref([...props.plugins]);
const loading = ref(false);
const error = ref(null);
const showSettings = ref(false);
const settingsPlugin = ref(null);
const settingsForm = ref({});
const savingSettings = ref(false);
const settingsError = ref(null);

const statusBadgeClass = (status) => {
    const map = {
        discovered: "bg-gray-200 text-gray-700",
        installed: "bg-blue-100 text-blue-700",
        active: "bg-green-100 text-green-700",
        inactive: "bg-yellow-100 text-yellow-800",
        error: "bg-red-100 text-red-700",
    };
    return map[status] ?? "bg-gray-200 text-gray-600";
};

async function apiCall(method, url, data = null) {
    loading.value = true;
    error.value = null;
    try {
        const resp = await axios({ method, url, data });
        return resp.data;
    } catch (err) {
        error.value = err?.response?.data?.message ?? err.message ?? "Request failed";
        return null;
    } finally {
        loading.value = false;
    }
}

async function refreshList() {
    const data = await apiCall("get", "/api/v1/admin/plugins");
    if (data?.data) plugins.value = data.data;
}

async function install(name) { if (await apiCall("post", "/api/v1/admin/plugins/" + name + "/install")) await refreshList(); }
async function activate(name) { if (await apiCall("post", "/api/v1/admin/plugins/" + name + "/activate")) await refreshList(); }
async function deactivate(name) { if (await apiCall("post", "/api/v1/admin/plugins/" + name + "/deactivate")) await refreshList(); }
async function uninstall(name) {
    if (!confirm("Uninstall plugin " + name + "?")) return;
    if (await apiCall("post", "/api/v1/admin/plugins/" + name + "/uninstall")) await refreshList();
}

function openSettings(plugin) {
    settingsPlugin.value = plugin;
    const form = {};
    (plugin.settings ?? []).forEach((s) => { form[s.key] = s.value ?? ""; });
    settingsForm.value = form;
    settingsError.value = null;
    showSettings.value = true;
}

function closeSettings() { showSettings.value = false; settingsPlugin.value = null; }

async function saveSettings() {
    savingSettings.value = true;
    settingsError.value = null;
    try {
        await axios.patch("/api/v1/admin/plugins/" + settingsPlugin.value.name + "/settings", { settings: settingsForm.value });
        await refreshList();
        closeSettings();
    } catch (err) {
        settingsError.value = err?.response?.data?.message ?? "Failed to save";
    } finally {
        savingSettings.value = false;
    }
}

const isSecret = (key) => /secret|password|token|key|api/i.test(key);
</script>

<template>
    <Head title="Plugins" />
    <MainLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white">Plugins</h1>
                    <p class="text-sm text-gray-400 mt-1">Manage installed and available plugins</p>
                </div>
                <button @click="refreshList" :disabled="loading"
                    class="flex items-center gap-2 rounded-lg bg-gray-800 px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 disabled:opacity-50 transition">
                    Refresh
                </button>
            </div>
            <div v-if="error" class="rounded-lg bg-red-900/40 border border-red-700 px-4 py-3 text-sm text-red-300">{{ error }}</div>
            <div class="rounded-xl bg-gray-900 border border-gray-800 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-800 bg-gray-800/40">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Plugin</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Version</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-400 uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-400 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="plugins.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center text-gray-500">No plugins found.</td>
                        </tr>
                        <tr v-for="plugin in plugins" :key="plugin.name"
                            class="border-b border-gray-800 last:border-0 hover:bg-gray-800/30 transition">
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="text-xl">&#x1F9E9;</span>
                                    <div>
                                        <p class="font-semibold text-white">{{ plugin.display_name ?? plugin.name }}</p>
                                        <p class="text-xs text-gray-500 mt-0.5">{{ plugin.description }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-gray-400 font-mono text-xs">{{ plugin.version ?? '-' }}</td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                    :class="statusBadgeClass(plugin.status)">{{ plugin.status }}</span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <button v-if="plugin.status === 'discovered'" @click="install(plugin.name)"
                                        :disabled="loading"
                                        class="rounded-md bg-blue-600 px-3 py-1.5 text-xs text-white hover:bg-blue-500 disabled:opacity-50 transition">Install</button>
                                    <button v-if="plugin.status === 'installed' || plugin.status === 'inactive'" @click="activate(plugin.name)"
                                        :disabled="loading"
                                        class="rounded-md bg-green-600 px-3 py-1.5 text-xs text-white hover:bg-green-500 disabled:opacity-50 transition">Activate</button>
                                    <button v-if="plugin.status === 'active'" @click="deactivate(plugin.name)"
                                        :disabled="loading"
                                        class="rounded-md bg-yellow-600 px-3 py-1.5 text-xs text-white hover:bg-yellow-500 disabled:opacity-50 transition">Deactivate</button>
                                    <button v-if="plugin.status === 'active'" @click="openSettings(plugin)"
                                        class="rounded-md bg-gray-700 px-3 py-1.5 text-xs text-gray-300 hover:bg-gray-600 transition">Settings</button>
                                    <button v-if="plugin.status !== 'discovered'" @click="uninstall(plugin.name)"
                                        :disabled="loading"
                                        class="rounded-md bg-red-900/60 px-3 py-1.5 text-xs text-red-300 hover:bg-red-800/60 disabled:opacity-50 transition">Uninstall</button>
                                    <a :href="'/admin/plugins/' + plugin.name"
                                        class="rounded-md bg-gray-700 px-3 py-1.5 text-xs text-gray-300 hover:bg-gray-600 transition">Details</a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <Teleport to="body">
            <div v-if="showSettings"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
                @click.self="closeSettings">
                <div class="w-full max-w-lg rounded-xl bg-gray-900 border border-gray-700 shadow-2xl">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                        <h2 class="text-lg font-semibold text-white">Settings - {{ settingsPlugin?.display_name ?? settingsPlugin?.name }}</h2>
                        <button @click="closeSettings" class="text-gray-400 hover:text-white transition">x</button>
                    </div>
                    <div class="px-6 py-5 space-y-4 max-h-96 overflow-y-auto">
                        <template v-if="settingsPlugin?.settings?.length">
                            <div v-for="setting in settingsPlugin.settings" :key="setting.key" class="flex flex-col gap-1">
                                <label class="text-xs font-medium text-gray-400 uppercase tracking-wider">{{ setting.key }}</label>
                                <input v-model="settingsForm[setting.key]"
                                    :type="isSecret(setting.key) ? 'password' : 'text'"
                                    :placeholder="setting.key"
                                    class="rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-sm text-white placeholder-gray-600 focus:border-indigo-500 focus:outline-none" />
                            </div>
                        </template>
                        <p v-else class="text-sm text-gray-500">No configurable settings.</p>
                        <p v-if="settingsError" class="text-sm text-red-400">{{ settingsError }}</p>
                    </div>
                    <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-800">
                        <button @click="closeSettings"
                            class="rounded-lg bg-gray-800 px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 transition">Cancel</button>
                        <button @click="saveSettings" :disabled="savingSettings"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-500 disabled:opacity-50 transition">
                            {{ savingSettings ? 'Saving...' : 'Save Settings' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </MainLayout>
</template>
