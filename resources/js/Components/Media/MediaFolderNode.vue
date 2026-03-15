<script setup>
import { computed } from 'vue';
import MediaFolderNode from './MediaFolderNode.vue';

const props = defineProps({
    folder:             { type: Object,  required: true },
    childrenMap:        { type: Object,  required: true },
    selectedFolderId:   { type: Number,  default: null },
    expanded:           { type: Object,  required: true },
    addingUnderParent:  { default: undefined },
    newFolderName:      { type: String,  default: '' },
    saving:             { type: Boolean, default: false },
    depth:              { type: Number,  default: 0 },
});

const emit = defineEmits(['select', 'toggle', 'start-add', 'cancel-add', 'confirm-add', 'update:newFolderName']);

const children   = computed(() => props.childrenMap[props.folder.id] ?? []);
const hasKids    = computed(() => children.value.length > 0);
const isOpen     = computed(() => props.expanded.has(props.folder.id));
const isActive   = computed(() => props.selectedFolderId === props.folder.id);
const paddingLeft = computed(() => `${props.depth * 12 + 8}px`);
</script>

<template>
    <div>
        <!-- Folder row -->
        <div
            class="group flex items-center gap-1 w-full text-left py-1.5 pr-2 rounded-lg text-sm cursor-pointer transition-colors"
            :class="isActive ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:bg-gray-100'"
            :style="{ paddingLeft }"
            @click="emit('select', folder.id)"
        >
            <!-- Expand arrow -->
            <button
                type="button"
                class="w-4 h-4 flex-shrink-0 text-gray-400 hover:text-gray-600 transition-transform"
                :class="{ 'invisible': !hasKids }"
                :style="{ transform: isOpen ? 'rotate(90deg)' : 'rotate(0deg)' }"
                @click.stop="emit('toggle', folder.id)"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            <!-- Folder icon -->
            <svg class="w-4 h-4 flex-shrink-0 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
            </svg>

            <!-- Name -->
            <span class="flex-1 truncate">{{ folder.name }}</span>

            <!-- Asset count -->
            <span v-if="folder.asset_count > 0" class="text-xs text-gray-400 ml-auto">
                {{ folder.asset_count }}
            </span>

            <!-- Add child button -->
            <button
                type="button"
                title="Add subfolder"
                class="opacity-0 group-hover:opacity-100 ml-1 p-0.5 rounded hover:bg-gray-200 text-gray-400 hover:text-gray-700"
                @click.stop="emit('start-add', folder.id)"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </button>
        </div>

        <!-- Inline add child folder input -->
        <div
            v-if="addingUnderParent === folder.id"
            class="py-1 pr-2"
            :style="{ paddingLeft: `${(depth + 1) * 12 + 8}px` }"
        >
            <input
                :value="newFolderName"
                type="text"
                placeholder="Folder name…"
                autofocus
                :disabled="saving"
                class="w-full px-2 py-1 text-xs border border-indigo-400 rounded focus:outline-none focus:ring-1 focus:ring-indigo-500"
                @input="emit('update:newFolderName', $event.target.value)"
                @keydown.enter="emit('confirm-add')"
                @keydown.esc="emit('cancel-add')"
                @blur="() => setTimeout(() => emit('cancel-add'), 150)"
            />
        </div>

        <!-- Children (lazy — only when expanded) -->
        <template v-if="isOpen">
            <MediaFolderNode
                v-for="child in children"
                :key="child.id"
                :folder="child"
                :children-map="childrenMap"
                :selected-folder-id="selectedFolderId"
                :expanded="expanded"
                :adding-under-parent="addingUnderParent"
                :new-folder-name="newFolderName"
                :saving="saving"
                :depth="depth + 1"
                @select="emit('select', $event)"
                @toggle="emit('toggle', $event)"
                @start-add="emit('start-add', $event)"
                @cancel-add="emit('cancel-add')"
                @confirm-add="emit('confirm-add')"
                @update:new-folder-name="emit('update:newFolderName', $event)"
            />
        </template>
    </div>
</template>
