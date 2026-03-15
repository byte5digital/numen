<script setup>
import { ref, computed } from "vue";
import { Head } from "@inertiajs/vue3";
import MainLayout from "@/Layouts/MainLayout.vue";
import axios from "axios";

const props = defineProps({
    plugin: { type: Object, required: true },
});

const pluginData = ref({ ...props.plugin });
const settingsForm = ref({});
const saving = ref(false);
const saveError = ref(null);
const saveSuccess = ref(false);

// Build form from settings array
const initForm = () => {
    const form = {};
    (pluginData.value.settings ?? []).forEach((s) => {
        form[s.key] = s.value ?? "";
    });
    settingsForm.value = form;
};

initForm();

const isSecret = (key) => /secret|password|token|key|api/i.test(key);

const manifest = computed(() => pluginData.value.manifest ?? {});

const settingsSchema = computed(() => {
    return manifest.value?.settings ?? [];
});

const hooks = computed(() => {
    return manifest.value?.hooks ?? [];
});

async function saveSettings() {
    saving.value = true;
    saveError.value = null;
    saveSuccess.value = false;
    try {
        await axios.patch("/api/v1/admin/plugins/" + pluginData.value.name + "/settings", {
            settings: settingsForm.value,
        });
        saveSuccess.value = true;
        setTimeout(() => { saveSuccess.value = false; }, 3000);
    } catch (err) {
        saveError.value = err?.response?.data?.message ?? "Failed to save settings";
    } finally {
        saving.value = false;
    }
}

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
</script>

<template>
    <Head :title="pluginData.display_name ?? pluginData.name" />
    <MainLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <a href="/admin/plugins" class="text-gray-400 hover:text-white transition">
                    &larr; Plugins
                </a>
                <div class="flex items-center gap-3 flex-1">
                    <span class="text-3xl">&#x1F9E9;</span>
                    <div>
                        <h1 class="text-2xl font-bold text-white">{{ pluginData.display_name ?? pluginData.name }}</h1>
                        <p class="text-sm text-gray-400">{{ pluginData.description }}</p>
                    </div>
                    <span class="ml-4 inline-flex items-center rounded-full px-3 py-1 text-sm font-medium capitalize"
                        :class="statusBadgeClass(pluginData.status)">{{ pluginData.status }}</span>
                </div>
            </div>

            <!-- Manifest info -->
            <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Plugin Info</h2>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500">Name</dt>
                        <dd class="text-white font-mono">{{ pluginData.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Version</dt>
                        <dd class="text-white font-mono">{{ pluginData.version ?? "-" }}</dd>
                    </div>
                    <div v-if="manifest.author">
                        <dt class="text-gray-500">Author</dt>
                        <dd class="text-white">{{ manifest.author }}</dd>
                    </div>
                    <div v-if="manifest.requires_numen">
                        <dt class="text-gray-500">Requires Numen</dt>
                        <dd class="text-white font-mono">{{ manifest.requires_numen }}</dd>
                    </div>
                    <div v-if="manifest.dependencies?.length">
                        <dt class="text-gray-500">Dependencies</dt>
                        <dd class="text-white font-mono text-xs">{{ manifest.dependencies.join(", ") }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Hooks -->
            <div v-if="hooks.length" class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Registered Hooks</h2>
                <div class="space-y-2">
                    <div v-for="hook in hooks" :key="hook" class="flex items-center gap-2 text-sm">
                        <span class="text-indigo-400 font-mono">{{ hook }}</span>
                    </div>
                </div>
            </div>

            <!-- Settings editor -->
            <div class="rounded-xl bg-gray-900 border border-gray-800 p-6">
                <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-4">Settings</h2>

                <div v-if="pluginData.settings?.length || settingsSchema.length" class="space-y-4">
                    <!-- Dynamic fields from schema if available -->
                    <template v-if="settingsSchema.length">
                        <div v-for="field in settingsSchema" :key="field.key" class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-gray-300">
                                {{ field.label ?? field.key }}
                                <span v-if="field.required" class="text-red-400 ml-0.5">*</span>
                            </label>
                            <p v-if="field.description" class="text-xs text-gray-500">{{ field.description }}</p>
                            <input
                                v-model="settingsForm[field.key]"
                                :type="field.type === 'secret' || isSecret(field.key) ? 'password' : field.type ?? 'text'"
                                :placeholder="field.default ?? field.key"
                                class="rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-sm text-white placeholder-gray-600 focus:border-indigo-500 focus:outline-none"
                            />
                        </div>
                    </template>
                    <!-- Fallback: fields from stored settings -->
                    <template v-else-if="pluginData.settings?.length">
                        <div v-for="setting in pluginData.settings" :key="setting.key" class="flex flex-col gap-1.5">
                            <label class="text-sm font-medium text-gray-300">{{ setting.key }}</label>
                            <input
                                v-model="settingsForm[setting.key]"
                                :type="isSecret(setting.key) ? 'password' : 'text'"
                                :placeholder="setting.key"
                                class="rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-sm text-white placeholder-gray-600 focus:border-indigo-500 focus:outline-none"
                            />
                        </div>
                    </template>

                    <!-- Save feedback -->
                    <div v-if="saveSuccess" class="text-sm text-green-400">Settings saved successfully.</div>
                    <div v-if="saveError" class="text-sm text-red-400">{{ saveError }}</div>

                    <div class="flex justify-end pt-2">
                        <button @click="saveSettings" :disabled="saving"
                            class="rounded-lg bg-indigo-600 px-5 py-2 text-sm text-white hover:bg-indigo-500 disabled:opacity-50 transition">
                            {{ saving ? "Saving..." : "Save Settings" }}
                        </button>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-500">This plugin has no configurable settings.</p>
            </div>
        </div>
    </MainLayout>
</template>
