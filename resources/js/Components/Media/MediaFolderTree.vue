<script setup>
import { ref, computed } from 'vue';
import MediaFolderNode from './MediaFolderNode.vue';

const props = defineProps({
    folders:          { type: Array,  default: () => [] },
    selectedFolderId: { type: Number, default: null },
});

const emit = defineEmits(['select', 'refresh']);

const expanded          = ref(new Set());
const addingUnderParent = ref(undefined);
const newFolderName     = ref('');
const saving            = ref(false);

const childrenMap = computed(() => {
    const map = {};
    for (const folder of props.folders) {
        const pid = folder.parent_id ?? 'root';
        if (!map[pid]) map[pid] = [];
        map[pid].push(folder);
    }
    return map;
});

const rootFolders = computed(() => childrenMap.value['root'] ?? []);

function toggleExpand(folderId) {
    const s = new Set(expanded.value);
    s.has(folderId) ? s.delete(folderId) : s.add(folderId);
    expanded.value = s;
}

function selectFolder(id) {
    emit('select', id);
}

function startAddFolder(parentId) {
    addingUnderParent.value = parentId;
    newFolderName.value = '';
}

function cancelAdd() {
    addingUnderParent.value = undefined;
    newFolderName.value = '';
}

async function confirmAdd() {
    const name = newFolderName.value.trim();
    if (!name) return;
    saving.value = true;
    try {
        const res = await fetch('/api/v1/media/folders', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ name, parent_id: addingUnderParent.value ?? null }),
        });
        if (res.ok) {
            if (addingUnderParent.value != null) {
                const s = new Set(expanded.value);
                s.add(addingUnderParent.value);
                expanded.value = s;
            }
            emit('refresh');
        }
    } catch { /* swallow */ }
    saving.value = false;
    cancelAdd();
}
</script>

<template>
    <nav class="w-56 flex-shrink-0 flex flex-col bg-gray-50 border-r border-gray-200 h-full select-none">
        <!-- Header -->
        <div class="flex items-center justify-between px-3 pt-4 pb-2">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Folders</span>
            <button
                type="button"
                title="New root folder"
                class="p-1 rounded hover:bg-gray-200 text-gray-500 hover:text-gray-800 transition-colors"
                @click="startAddFolder(null)"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </button>
        </div>

        <!-- All Files -->
        <button
            type="button"
            class="flex items-center gap-2 w-full text-left px-3 py-2 text-sm rounded-lg mx-1 transition-colors"
            :class="selectedFolderId === null
                ? 'bg-indigo-50 text-indigo-700 font-medium'
                : 'text-gray-700 hover:bg-gray-100'"
            @click="selectFolder(null)"
        >
            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
            </svg>
            All Files
        </button>

        <!-- Inline add root folder input -->
        <div v-if="addingUnderParent === null" class="px-2 py-1">
            <input
                v-model="newFolderName"
                type="text"
                placeholder="Folder name…"
                autofocus
                :disabled="saving"
                class="w-full px-2 py-1 text-sm border border-indigo-400 rounded focus:outline-none focus:ring-2 focus:ring-indigo-500"
                @keydown.enter="confirmAdd"
                @keydown.esc="cancelAdd"
                @blur="() => setTimeout(cancelAdd, 150)"
            />
        </div>

        <!-- Folder tree -->
        <div class="flex-1 overflow-y-auto pb-4 px-1">
            <MediaFolderNode
                v-for="folder in rootFolders"
                :key="folder.id"
                :folder="folder"
                :children-map="childrenMap"
                :selected-folder-id="selectedFolderId"
                :expanded="expanded"
                :adding-under-parent="addingUnderParent"
                :new-folder-name="newFolderName"
                :saving="saving"
                :depth="0"
                @select="selectFolder"
                @toggle="toggleExpand"
                @start-add="startAddFolder"
                @cancel-add="cancelAdd"
                @confirm-add="confirmAdd"
                @update:new-folder-name="newFolderName = $event"
            />

            <div v-if="rootFolders.length === 0" class="px-3 py-4 text-xs text-gray-400 text-center">
                No folders yet
            </div>
        </div>
    </nav>
</template>
