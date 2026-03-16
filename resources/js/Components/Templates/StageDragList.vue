<script setup lang="ts">
import { ref } from 'vue'

interface Stage {
  _uid: string
  name: string
  type: string
  agent_role: string | null
  model: string | null
  prompt_key: string | null
  timeout_seconds: number | null
  allow_failure: boolean
  human_gate: boolean
  config: Record<string, unknown>
}

interface StageType {
  value: string
  label: string
}

const props = defineProps<{
  modelValue: Stage[]
  stageTypes: StageType[]
  stageColorClass: (type: string) => string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', stages: Stage[]): void
  (e: 'remove', uid: string): void
}>()

const expandedStages = ref<Set<string>>(new Set())
const dragSrcIdx = ref<number | null>(null)
const dragOverIdx = ref<number | null>(null)

function toggleExpand(uid: string): void {
  if (expandedStages.value.has(uid)) {
    expandedStages.value.delete(uid)
  } else {
    expandedStages.value.add(uid)
  }
}

function onDragStart(idx: number, ev: DragEvent): void {
  dragSrcIdx.value = idx
  if (ev.dataTransfer) {
    ev.dataTransfer.effectAllowed = 'move'
    ev.dataTransfer.setData('text/plain', String(idx))
  }
}

function onDragOver(idx: number, ev: DragEvent): void {
  ev.preventDefault()
  dragOverIdx.value = idx
  if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'move'
}

function onDragLeave(): void {
  dragOverIdx.value = null
}

function onDrop(targetIdx: number, ev: DragEvent): void {
  ev.preventDefault()
  const srcIdx = dragSrcIdx.value
  if (srcIdx === null || srcIdx === targetIdx) {
    dragSrcIdx.value = null
    dragOverIdx.value = null
    return
  }
  const stages = [...props.modelValue]
  const [moved] = stages.splice(srcIdx, 1)
  stages.splice(targetIdx, 0, moved)
  emit('update:modelValue', stages)
  dragSrcIdx.value = null
  dragOverIdx.value = null
}

function onDragEnd(): void {
  dragSrcIdx.value = null
  dragOverIdx.value = null
}

function updateField(uid: string, field: keyof Stage, value: unknown): void {
  const stages = props.modelValue.map((s) =>
    s._uid === uid ? { ...s, [field]: value } : s,
  )
  emit('update:modelValue', stages)
}
</script>

<template>
  <div class="space-y-2">
    <div
      v-for="(stage, idx) in modelValue"
      :key="stage._uid"
      draggable="true"
      class="rounded-xl border transition-all duration-150 cursor-grab active:cursor-grabbing"
      :class="[
        stageColorClass(stage.type),
        dragOverIdx === idx ? 'ring-2 ring-indigo-500 ring-offset-1 ring-offset-gray-950' : '',
        dragSrcIdx === idx ? 'opacity-50' : '',
      ]"
      @dragstart="onDragStart(idx, $event)"
      @dragover="onDragOver(idx, $event)"
      @dragleave="onDragLeave"
      @drop="onDrop(idx, $event)"
      @dragend="onDragEnd"
    >
      <!-- Stage header -->
      <div class="flex items-center gap-3 px-4 py-3" @click="toggleExpand(stage._uid)">
        <span class="text-gray-600 select-none">⠿</span>
        <span class="text-xs font-mono text-gray-600">{{ idx + 1 }}</span>
        <span class="font-medium text-sm flex-1">{{ stage.name || 'Unnamed Stage' }}</span>
        <span class="text-xs opacity-60">{{ stage.type }}</span>
        <button
          class="ml-2 text-xs opacity-40 hover:opacity-100 transition"
          @click.stop="emit('remove', stage._uid)"
        >
          ✕
        </button>
        <span class="text-xs opacity-40 ml-1">{{ expandedStages.has(stage._uid) ? '▲' : '▼' }}</span>
      </div>

      <!-- Expanded editor -->
      <div v-if="expandedStages.has(stage._uid)" class="px-4 pb-4 border-t border-current/10 pt-3 space-y-3">
        <div class="grid grid-cols-2 gap-3">
          <label class="block">
            <span class="text-xs opacity-60 mb-1 block">Stage Name</span>
            <input
              :value="stage.name"
              type="text"
              class="w-full px-3 py-1.5 bg-black/20 border border-current/20 rounded-lg text-sm focus:outline-none focus:border-current/50"
              @input="updateField(stage._uid, 'name', ($event.target as HTMLInputElement).value)"
            />
          </label>
          <label class="block">
            <span class="text-xs opacity-60 mb-1 block">Stage Type</span>
            <select
              :value="stage.type"
              class="w-full px-3 py-1.5 bg-black/20 border border-current/20 rounded-lg text-sm focus:outline-none focus:border-current/50"
              @change="updateField(stage._uid, 'type', ($event.target as HTMLSelectElement).value)"
            >
              <option v-for="st in stageTypes" :key="st.value" :value="st.value">{{ st.label }}</option>
            </select>
          </label>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <label class="block">
            <span class="text-xs opacity-60 mb-1 block">Agent Role</span>
            <input
              :value="stage.agent_role ?? ''"
              type="text"
              class="w-full px-3 py-1.5 bg-black/20 border border-current/20 rounded-lg text-sm focus:outline-none focus:border-current/50"
              placeholder="e.g. writer"
              @input="updateField(stage._uid, 'agent_role', ($event.target as HTMLInputElement).value || null)"
            />
          </label>
          <label class="block">
            <span class="text-xs opacity-60 mb-1 block">Model</span>
            <input
              :value="stage.model ?? ''"
              type="text"
              class="w-full px-3 py-1.5 bg-black/20 border border-current/20 rounded-lg text-sm focus:outline-none focus:border-current/50"
              placeholder="e.g. gpt-4o"
              @input="updateField(stage._uid, 'model', ($event.target as HTMLInputElement).value || null)"
            />
          </label>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <label class="block">
            <span class="text-xs opacity-60 mb-1 block">Prompt Key</span>
            <input
              :value="stage.prompt_key ?? ''"
              type="text"
              class="w-full px-3 py-1.5 bg-black/20 border border-current/20 rounded-lg text-sm focus:outline-none focus:border-current/50"
              placeholder="e.g. blog.draft"
              @input="updateField(stage._uid, 'prompt_key', ($event.target as HTMLInputElement).value || null)"
            />
          </label>
          <label class="block">
            <span class="text-xs opacity-60 mb-1 block">Timeout (seconds)</span>
            <input
              :value="stage.timeout_seconds ?? ''"
              type="number"
              class="w-full px-3 py-1.5 bg-black/20 border border-current/20 rounded-lg text-sm focus:outline-none focus:border-current/50"
              placeholder="120"
              @input="updateField(stage._uid, 'timeout_seconds', Number(($event.target as HTMLInputElement).value) || null)"
            />
          </label>
        </div>

        <div class="flex gap-4">
          <label class="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              :checked="stage.allow_failure"
              class="rounded"
              @change="updateField(stage._uid, 'allow_failure', ($event.target as HTMLInputElement).checked)"
            />
            <span class="text-xs opacity-60">Allow failure</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input
              type="checkbox"
              :checked="stage.human_gate"
              class="rounded"
              @change="updateField(stage._uid, 'human_gate', ($event.target as HTMLInputElement).checked)"
            />
            <span class="text-xs opacity-60">Human gate</span>
          </label>
        </div>
      </div>
    </div>
  </div>
</template>
