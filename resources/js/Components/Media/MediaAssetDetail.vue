<script setup>
import { ref, computed, watch } from "vue";
import axios from "axios";
import MediaTagEditor from "./MediaTagEditor.vue";
import MediaUsageList from "./MediaUsageList.vue";

const props = defineProps({
    asset: { type: Object, default: null },
    open: { type: Boolean, default: false },
    folders: { type: Array, default: () => [] },
});

const emit = defineEmits(["close", "updated", "deleted"]);

const form = ref({ alt_text: "", caption: "", tags: [], folder_id: null });
const saving = ref(false);
const deleting = ref(false);
const saveError = ref(null);
const saveSuccess = ref(false);
const copied = ref(false);
const showDeleteConfirm = ref(false);

watch(() => props.asset, (asset) => {
    if (asset) {
        form.value = {
            alt_text: asset.alt_text ?? "",
            caption: asset.caption ?? "",
            tags: Array.isArray(asset.tags) ? [...asset.tags] : [],
            folder_id: asset.folder_id ?? null,
        };
        saveError.value = null;
        saveSuccess.value = false;
        showDeleteConfirm.value = false;
    }
}, { immediate: true });

const isImage = computed(() => props.asset?.mime_type?.startsWith("image/"));
const isVideo = computed(() => props.asset?.mime_type?.startsWith("video/"));
const isAudio = computed(() => props.asset?.mime_type?.startsWith("audio/"));
const assetUrl = computed(() => props.asset?.url ?? props.asset?.storage_path ?? "");

const formattedSize = computed(() => {
    const bytes = props.asset?.file_size ?? 0;
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + " KB";
    return (bytes / 1048576).toFixed(1) + " MB";
});

const dimensions = computed(() => {
    const { width, height } = props.asset ?? {};
    if (width && height) return width + " x " + height;
    return null;
});

async function save() {
    saving.value = true;
    saveError.value = null;
    saveSuccess.value = false;
    try {
        const res = await axios.patch("/api/v1/media/" + props.asset.id, {
            alt_text: form.value.alt_text || null,
            caption: form.value.caption || null,
            tags: form.value.tags,
            folder_id: form.value.folder_id || null,
        });
        saveSuccess.value = true;
        emit("updated", res.data.data);
        setTimeout(() => { saveSuccess.value = false; }, 2500);
    } catch (e) {
        saveError.value = e?.response?.data?.message ?? "Failed to save.";
    } finally {
        saving.value = false;
    }
}

async function deleteAsset() {
    deleting.value = true;
    try {
        await axios.delete("/api/v1/media/" + props.asset.id);
        emit("deleted");
        emit("close");
    } catch (e) {
        saveError.value = e?.response?.data?.message ?? "Failed to delete.";
        showDeleteConfirm.value = false;
    } finally {
        deleting.value = false;
    }
}

async function copyUrl() {
    try {
        await navigator.clipboard.writeText(assetUrl.value);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch {}
}

function close() {
    showDeleteConfirm.value = false;
    emit("close");
}
<\/script>

<template>
    <Transition name="backdrop">
        <div v-if="open" class="fixed inset-0 bg-black/60 z-40" @click="close" />
    </Transition>
    <Transition name="slideover">
        <div v-if="open && asset" class="fixed right-0 top-0 bottom-0 w-full max-w-xl bg-gray-900 border-l border-gray-800 z-50 flex flex-col overflow-hidden shadow-2xl">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800 flex-shrink-0">
                <h2 class="text-base font-semibold text-white truncate pr-4">{{ asset.filename }}</h2>
                <button type="button" class="text-gray-400 hover:text-white transition" @click="close" aria-label="Close">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <!-- Body -->
            <div class="flex-1 overflow-y-auto">
                <!-- Preview -->
                <div class="bg-gray-950 flex items-center justify-center" style="min-height:220px;max-height:320px;">
                    <img v-if="isImage && assetUrl" :src="assetUrl" :alt="asset.alt_text || asset.filename" class="max-h-80 max-w-full object-contain" />
                    <video v-else-if="isVideo && assetUrl" :src="assetUrl" controls class="max-h-80 max-w-full" />
                    <audio v-else-if="isAudio && assetUrl" :src="assetUrl" controls class="w-full px-6" />
                    <div v-else class="flex flex-col items-center gap-3 text-gray-600">
                        <svg class="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        <span class="text-xs font-mono">{{ asset.mime_type }}</span>
                    </div>
                </div>
                <!-- Fields -->
                <div class="px-6 py-5 space-y-6">
                    <!-- Info grid -->
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                        <div><span class="text-gray-500 text-xs uppercase tracking-wide">Size</span><p class="text-gray-200 mt-0.5">{{ formattedSize }}</p></div>
                        <div v-if="dimensions"><span class="text-gray-500 text-xs uppercase tracking-wide">Dimensions</span><p class="text-gray-200 mt-0.5">{{ dimensions }}</p></div>
                        <div class="col-span-2"><span class="text-gray-500 text-xs uppercase tracking-wide">Type</span><p class="text-gray-200 mt-0.5 font-mono text-xs">{{ asset.mime_type }}</p></div>
                    </div>
                    <!-- URL -->
                    <div v-if="assetUrl">
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1.5">File URL</label>
                        <div class="flex items-center gap-2">
                            <input :value="assetUrl" readonly class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs text-gray-300 font-mono truncate" />
                            <button type="button" class="flex-shrink-0 px-3 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 hover:text-white rounded-lg text-xs transition" @click="copyUrl">{{ copied ? 'Copied!' : 'Copy' }}</button>
                        </div>
                    </div>
                    <!-- Alt text -->
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">Alt Text</label>
                        <input v-model="form.alt_text" type="text" placeholder="Describe this image..." class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500" />
                    </div>
                    <!-- Caption -->
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">Caption</label>
                        <textarea v-model="form.caption" rows="3" placeholder="Optional caption..." class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 resize-none" />
                    </div>
                    <!-- Tags -->
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">Tags</label>
                        <MediaTagEditor v-model="form.tags" />
                    </div>
                    <!-- Folder -->
                    <div v-if="folders.length > 0">
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">Folder</label>
                        <select v-model="form.folder_id" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                            <option :value="null">Root (no folder)</option>
                            <option v-for="folder in folders" :key="folder.id" :value="folder.id">{{ folder.name }}</option>
                        </select>
                    </div>
                    <!-- Feedback -->
                    <div v-if="saveError" class="text-sm text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg px-3 py-2">{{ saveError }}</div>
                    <div v-if="saveSuccess" class="text-sm text-green-400 bg-green-500/10 border border-green-500/20 rounded-lg px-3 py-2">Saved successfully.</div>
                    <!-- Save -->
                    <button type="button" class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2" :disabled="saving" @click="save">
                        <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                        {{ saving ? 'Saving...' : 'Save Metadata' }}
                    </button>
                    <!-- Usage -->
                    <div>
                        <h3 class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Usage</h3>
                        <MediaUsageList :asset-id="asset.id" />
                    </div>
                    <!-- Delete -->
                    <div class="border-t border-gray-800 pt-5">
                        <h3 class="text-xs font-medium text-red-500/70 uppercase tracking-wide mb-3">Danger Zone</h3>
                        <div v-if="!showDeleteConfirm">
                            <button type="button" class="w-full py-2.5 bg-red-600/20 hover:bg-red-600/30 text-red-400 hover:text-red-300 border border-red-500/30 text-sm font-medium rounded-lg transition" @click="showDeleteConfirm = true">Delete Asset</button>
                        </div>
                        <div v-else class="bg-red-950/30 border border-red-500/30 rounded-lg p-4 space-y-3">
                            <p class="text-sm text-red-300">Permanently delete <strong>{{ asset.filename }}</strong>? This cannot be undone.</p>
                            <div class="flex gap-2">
                                <button type="button" class="flex-1 py-2 bg-red-600 hover:bg-red-500 text-white text-sm font-medium rounded-lg transition disabled:opacity-50 flex items-center justify-center gap-2" :disabled="deleting" @click="deleteAsset">{{ deleting ? 'Deleting...' : 'Yes, Delete' }}</button>
                                <button type="button" class="flex-1 py-2 bg-gray-700 hover:bg-gray-600 text-gray-200 text-sm font-medium rounded-lg transition" @click="showDeleteConfirm = false">Cancel</button>
                            </div>
                        </div>
                    </div>
                    <div class="h-4" />
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.backdrop-enter-active,.backdrop-leave-active{transition:opacity .2s ease;}
.backdrop-enter-from,.backdrop-leave-to{opacity:0;}
.slideover-enter-active,.slideover-leave-active{transition:transform .25s ease;}
.slideover-enter-from,.slideover-leave-to{transform:translateX(100%);}
</style>
