<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  asset: { type: Object, default: null },
  open: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'saved'])

const activeTab = ref('crop')
const cropX = ref(0)
const cropY = ref(0)
const cropW = ref(100)
const cropH = ref(100)
const isDragging = ref(false)
const dragHandle = ref(null)
const dragStart = ref({ x: 0, y: 0 })
const previewRef = ref(null)
const rotationAngle = ref(0)
const resizeW = ref(null)
const resizeH = ref(null)
const aspectLocked = ref(true)
const saveAsVariant = ref(true)
const processing = ref(false)
const error = ref(null)

const originalAspect = computed(() =>
  props.asset?.width && props.asset?.height ? props.asset.width / props.asset.height : 1
)

watch(() => [props.asset, props.open], () => {
  if (props.asset && props.open) {
    cropX.value = 0; cropY.value = 0; cropW.value = 100; cropH.value = 100
    rotationAngle.value = 0
    resizeW.value = props.asset.width ?? null
    resizeH.value = props.asset.height ?? null
    error.value = null; activeTab.value = 'crop'
  }
})

const cropStyle = computed(() => ({
  left: cropX.value + '%', top: cropY.value + '%',
  width: cropW.value + '%', height: cropH.value + '%',
}))

const imageTransformStyle = computed(() => ({
  transform: `rotate(${rotationAngle.value}deg)`,
  transition: 'transform 0.2s ease', maxWidth: '100%', maxHeight: '100%', objectFit: 'contain',
}))

function clamp(val, min, max) { return Math.min(Math.max(val, min), max) }

function startDrag(e, handle) {
  isDragging.value = true; dragHandle.value = handle
  dragStart.value = { x: e.clientX, y: e.clientY }; e.preventDefault()
}

function onMouseMove(e) {
  if (!isDragging.value || !previewRef.value) return
  const rect = previewRef.value.getBoundingClientRect()
  const dx = ((e.clientX - dragStart.value.x) / rect.width) * 100
  const dy = ((e.clientY - dragStart.value.y) / rect.height) * 100
  dragStart.value = { x: e.clientX, y: e.clientY }
  const h = dragHandle.value
  if (h === 'move') {
    cropX.value = clamp(cropX.value + dx, 0, 100 - cropW.value)
    cropY.value = clamp(cropY.value + dy, 0, 100 - cropH.value)
  } else if (h === 'se') {
    cropW.value = clamp(cropW.value + dx, 5, 100 - cropX.value)
    cropH.value = clamp(cropH.value + dy, 5, 100 - cropY.value)
  } else if (h === 'sw') {
    const newX = clamp(cropX.value + dx, 0, cropX.value + cropW.value - 5)
    cropW.value = cropW.value + cropX.value - newX; cropX.value = newX
    cropH.value = clamp(cropH.value + dy, 5, 100 - cropY.value)
  } else if (h === 'ne') {
    cropW.value = clamp(cropW.value + dx, 5, 100 - cropX.value)
    const newY = clamp(cropY.value + dy, 0, cropY.value + cropH.value - 5)
    cropH.value = cropH.value + cropY.value - newY; cropY.value = newY
  } else if (h === 'nw') {
    const newX = clamp(cropX.value + dx, 0, cropX.value + cropW.value - 5)
    cropW.value = cropW.value + cropX.value - newX; cropX.value = newX
    const newY = clamp(cropY.value + dy, 0, cropY.value + cropH.value - 5)
    cropH.value = cropH.value + cropY.value - newY; cropY.value = newY
  }
}

function stopDrag() { isDragging.value = false; dragHandle.value = null }
function rotateLeft() { rotationAngle.value = ((rotationAngle.value - 90) % 360 + 360) % 360 }
function rotateRight() { rotationAngle.value = (rotationAngle.value + 90) % 360 }
function onWidthInput() { if (aspectLocked.value && resizeW.value) resizeH.value = Math.round(resizeW.value / originalAspect.value) }
function onHeightInput() { if (aspectLocked.value && resizeH.value) resizeW.value = Math.round(resizeH.value * originalAspect.value) }

function buildPayload() {
  if (activeTab.value === 'crop') {
    const w = props.asset?.width ?? 1000, h = props.asset?.height ?? 1000
    return { operation: 'crop', params: { x: Math.round(cropX.value/100*w), y: Math.round(cropY.value/100*h), width: Math.round(cropW.value/100*w), height: Math.round(cropH.value/100*h) }, save_as_variant: saveAsVariant.value }
  }
  if (activeTab.value === 'rotate') return { operation: 'rotate', params: { degrees: rotationAngle.value }, save_as_variant: saveAsVariant.value }
  if (activeTab.value === 'resize') return { operation: 'resize', params: { width: resizeW.value, height: resizeH.value, maintain_aspect: aspectLocked.value }, save_as_variant: saveAsVariant.value }
  return null
}

async function applyEdit() {
  const payload = buildPayload()
  if (!payload || !props.asset) return
  processing.value = true; error.value = null
  try {
    const res = await fetch(`/v1/media/${props.asset.id}/edit`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '' },
      body: JSON.stringify(payload),
    })
    const data = await res.json()
    if (!res.ok) { error.value = data?.message ?? 'Failed to apply edit.'; return }
    emit('saved', data)
  } catch { error.value = 'An unexpected error occurred.' }
  finally { processing.value = false }
}

function close() { if (!processing.value) emit('close') }
</script>
<template>
  <Teleport to="body">
    <div v-if="open && asset" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" @mouseup="stopDrag" @mousemove="onMouseMove">
      <div class="relative flex h-[90vh] w-full max-w-5xl flex-col rounded-xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b px-6 py-4">
          <h2 class="text-lg font-semibold text-gray-900">Edit Image</h2>
          <button class="rounded p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-50" :disabled="processing" @click="close">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>        <div class="flex min-h-0 flex-1">
          <div class="relative flex flex-1 items-center justify-center overflow-hidden bg-gray-100 p-4">
            <div ref="previewRef" class="relative inline-block select-none">
              <img :src="asset.url" :alt="asset.filename" :style="activeTab === 'rotate' ? imageTransformStyle : {}" class="block max-h-[65vh] max-w-full object-contain" draggable="false" />
              <template v-if="activeTab === 'crop'">
                <div class="pointer-events-none absolute inset-0 bg-black/40" />
                <div class="absolute border-2 border-white/80" :style="[cropStyle, { background: 'transparent', boxShadow: '0 0 0 9999px rgba(0,0,0,0.5)' }]" @mousedown="(e) => startDrag(e, 'move')">
                  <div class="absolute -left-1.5 -top-1.5 h-3 w-3 cursor-nw-resize rounded-sm bg-white shadow" @mousedown.stop="(e) => startDrag(e, 'nw')" />
                  <div class="absolute -right-1.5 -top-1.5 h-3 w-3 cursor-ne-resize rounded-sm bg-white shadow" @mousedown.stop="(e) => startDrag(e, 'ne')" />
                  <div class="absolute -bottom-1.5 -left-1.5 h-3 w-3 cursor-sw-resize rounded-sm bg-white shadow" @mousedown.stop="(e) => startDrag(e, 'sw')" />
                  <div class="absolute -bottom-1.5 -right-1.5 h-3 w-3 cursor-se-resize rounded-sm bg-white shadow" @mousedown.stop="(e) => startDrag(e, 'se')" />
                </div>
              </template>
            </div>
          </div>          <div class="flex w-72 flex-shrink-0 flex-col border-l bg-white">
            <div class="flex border-b">
              <button v-for="tab in ['crop', 'rotate', 'resize']" :key="tab" class="flex-1 py-3 text-sm font-medium capitalize transition-colors" :class="activeTab === tab ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500 hover:text-gray-700'" @click="activeTab = tab">
                {{ tab }}
              </button>
            </div>
            <div class="flex-1 overflow-y-auto p-5">
              <template v-if="activeTab === 'crop'">
                <p class="mb-4 text-sm text-gray-500">Drag the crop area or corner handles on the preview.</p>
                <div class="grid grid-cols-2 gap-3">
                  <div><label class="mb-1 block text-xs font-medium text-gray-700">X (%)</label><input v-model.number="cropX" type="number" min="0" max="95" class="w-full rounded-md border px-2.5 py-1.5 text-sm" /></div>
                  <div><label class="mb-1 block text-xs font-medium text-gray-700">Y (%)</label><input v-model.number="cropY" type="number" min="0" max="95" class="w-full rounded-md border px-2.5 py-1.5 text-sm" /></div>
                  <div><label class="mb-1 block text-xs font-medium text-gray-700">Width (%)</label><input v-model.number="cropW" type="number" min="5" max="100" class="w-full rounded-md border px-2.5 py-1.5 text-sm" /></div>
                  <div><label class="mb-1 block text-xs font-medium text-gray-700">Height (%)</label><input v-model.number="cropH" type="number" min="5" max="100" class="w-full rounded-md border px-2.5 py-1.5 text-sm" /></div>
                </div>
              </template>              <template v-else-if="activeTab === 'rotate'">
                <p class="mb-4 text-sm text-gray-500">Rotate the image in 90° increments.</p>
                <div class="flex gap-3">
                  <button class="flex flex-1 items-center justify-center gap-2 rounded-md border px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="rotateLeft">-90°</button>
                  <button class="flex flex-1 items-center justify-center gap-2 rounded-md border px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="rotateRight">+90°</button>
                </div>
                <p class="mt-3 text-center text-xs text-gray-400">Current: {{ rotationAngle }}°</p>
              </template>
              <template v-else-if="activeTab === 'resize'">
                <p class="mb-4 text-sm text-gray-500">Set the output dimensions in pixels.</p>
                <div class="space-y-3">
                  <div><label class="mb-1 block text-xs font-medium text-gray-700">Width (px)</label><input v-model.number="resizeW" type="number" min="1" class="w-full rounded-md border px-2.5 py-1.5 text-sm" @input="onWidthInput" /></div>
                  <div class="flex items-center justify-center">
                    <button class="flex items-center gap-1.5 rounded px-2 py-1 text-xs transition-colors" :class="aspectLocked ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-500'" @click="aspectLocked = !aspectLocked">
                      {{ aspectLocked ? 'Aspect locked' : 'Aspect free' }}
                    </button>
                  </div>
                  <div><label class="mb-1 block text-xs font-medium text-gray-700">Height (px)</label><input v-model.number="resizeH" type="number" min="1" class="w-full rounded-md border px-2.5 py-1.5 text-sm" @input="onHeightInput" /></div>
                </div>
              </template>
            </div>            <div class="border-t p-5">
              <div class="mb-4 flex items-center gap-2">
                <label class="relative inline-flex cursor-pointer items-center">
                  <input v-model="saveAsVariant" type="checkbox" class="peer sr-only" />
                  <div class="peer h-5 w-9 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-4 after:w-4 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:bg-indigo-600 peer-checked:after:translate-x-full" />
                </label>
                <span class="text-sm text-gray-700">{{ saveAsVariant ? 'Save as new variant' : 'Replace original' }}</span>
              </div>
              <div v-if="error" class="mb-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</div>
              <button class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="processing" @click="applyEdit">
                <svg v-if="processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 12 0 12 12H4z" /></svg>
                {{ processing ? 'Applying…' : 'Apply' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
