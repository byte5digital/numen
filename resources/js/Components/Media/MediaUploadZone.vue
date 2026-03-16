<script setup>
import { ref, reactive } from 'vue';

const props = defineProps({
    multiple: { type: Boolean, default: true },
    accept:   { type: String,  default: 'image/*' },
});

const emit = defineEmits(['uploaded', 'error']);

const dragging    = ref(false);
const fileInput   = ref(null);
const uploads     = reactive({}); // { [tempId]: { name, progress, status } }
let   tempCounter = 0;

// ── CSRF ─────────────────────────────────────────────────────────────────────

async function csrfCookie() {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
}

function xsrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

// ── Upload ────────────────────────────────────────────────────────────────────

async function uploadFile(file) {
    const id = ++tempCounter;
    uploads[id] = { name: file.name, progress: 0, status: 'uploading' };

    await csrfCookie();

    const formData = new FormData();
    formData.append('file', file);

    return new Promise((resolve) => {
        const xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                uploads[id].progress = Math.round((e.loaded / e.total) * 100);
            }
        });

        xhr.addEventListener('load', () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                uploads[id].status = 'done';
                setTimeout(() => delete uploads[id], 2000);
                try {
                    const asset = JSON.parse(xhr.responseText);
                    emit('uploaded', asset);
                    resolve({ ok: true, asset });
                } catch {
                    emit('error', 'Invalid response from server');
                    resolve({ ok: false });
                }
            } else {
                uploads[id].status = 'error';
                setTimeout(() => delete uploads[id], 4000);
                let msg = `Upload failed (${xhr.status})`;
                try {
                    const body = JSON.parse(xhr.responseText);
                    msg = body.message || msg;
                } catch {}
                emit('error', msg);
                resolve({ ok: false });
            }
        });

        xhr.addEventListener('error', () => {
            uploads[id].status = 'error';
            setTimeout(() => delete uploads[id], 4000);
            emit('error', 'Network error during upload');
            resolve({ ok: false });
        });

        xhr.open('POST', '/api/v1/media');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-XSRF-TOKEN', xsrfToken());
        xhr.withCredentials = true;
        xhr.send(formData);
    });
}

async function handleFiles(fileList) {
    const files = Array.from(fileList);
    if (!files.length) return;
    await Promise.all(files.map(uploadFile));
}

// ── Drag handlers ─────────────────────────────────────────────────────────────

function onDragOver(e) {
    e.preventDefault();
    dragging.value = true;
}

function onDragLeave(e) {
    // Only trigger if leaving the zone itself
    if (!e.currentTarget.contains(e.relatedTarget)) {
        dragging.value = false;
    }
}

function onDrop(e) {
    e.preventDefault();
    dragging.value = false;
    handleFiles(e.dataTransfer.files);
}

function onFileInputChange(e) {
    handleFiles(e.target.files);
    e.target.value = '';
}

function openFilePicker() {
    fileInput.value?.click();
}

const activeUploads = () => Object.values(uploads);
</script>

<template>
    <div>
        <!-- Drop zone -->
        <div
            class="relative border-2 border-dashed rounded-xl p-8 text-center transition-colors cursor-pointer"
            :class="dragging
                ? 'border-indigo-500 bg-indigo-950/30'
                : 'border-gray-700 hover:border-gray-500 bg-gray-900/40'"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
            @click="openFilePicker"
        >
            <input
                ref="fileInput"
                type="file"
                class="hidden"
                :multiple="multiple"
                :accept="accept"
                @change="onFileInputChange"
            />

            <div class="flex flex-col items-center gap-2 pointer-events-none select-none">
                <span class="text-3xl" :class="dragging ? 'animate-bounce' : ''">☁️</span>
                <p class="text-sm font-medium" :class="dragging ? 'text-indigo-300' : 'text-gray-400'">
                    {{ dragging ? 'Drop files to upload' : 'Drag & drop files here' }}
                </p>
                <p class="text-xs text-gray-600">or click to browse</p>
            </div>
        </div>

        <!-- Per-file progress bars -->
        <div v-if="activeUploads().length" class="mt-3 space-y-2">
            <div
                v-for="upload in activeUploads()"
                :key="upload.name + upload.progress"
                class="bg-gray-900 rounded-lg px-3 py-2"
            >
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs text-gray-400 truncate max-w-[70%]">{{ upload.name }}</span>
                    <span
                        class="text-xs font-medium"
                        :class="{
                            'text-indigo-400': upload.status === 'uploading',
                            'text-emerald-400': upload.status === 'done',
                            'text-red-400':    upload.status === 'error',
                        }"
                    >
                        {{ upload.status === 'uploading' ? upload.progress + '%' : upload.status }}
                    </span>
                </div>
                <div class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all duration-200"
                        :class="{
                            'bg-indigo-500': upload.status === 'uploading',
                            'bg-emerald-500': upload.status === 'done',
                            'bg-red-500':    upload.status === 'error',
                        }"
                        :style="{ width: upload.progress + '%' }"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
