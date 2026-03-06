<script setup>
/**
 * ComponentFieldEditor — renders per-type structured form fields driven by
 * the typeSchema passed from the backend. Supports string, text, number,
 * and array repeater fields.
 *
 * Schema format (mirrors PageComponent::typeSchema in PHP):
 *   { fieldKey: 'string' | 'text' | 'number' | 'wysiwyg' | 'array:subfield1,subfield2,...' }
 */
import { computed } from 'vue';

const props = defineProps({
    schema:     { type: Object, required: true },
    modelValue: { type: Object, required: true },
});

const emit = defineEmits(['update:modelValue']);

// Human-readable label from camelCase/snake_case key
const label = (key) =>
    key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

// Parse array sub-fields from schema value like 'array:icon,title,description'
const arrayFields = (schemaVal) =>
    schemaVal.replace('array:', '').split(',').map((f) => f.trim());

// Fields we render — skip 'wysiwyg' type (handled by HTML Override tab)
const fields = computed(() =>
    Object.entries(props.schema).filter(([, type]) => type !== 'wysiwyg')
);

// Update a simple top-level field
function setField(key, value) {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}

// Update a sub-field inside an array item
function setArrayItem(key, index, subKey, value) {
    const arr = [...(props.modelValue[key] ?? [])];
    arr[index] = { ...arr[index], [subKey]: value };
    emit('update:modelValue', { ...props.modelValue, [key]: arr });
}

function addArrayItem(key, subFields) {
    const blank = Object.fromEntries(subFields.map((f) => [f, '']));
    const arr = [...(props.modelValue[key] ?? []), blank];
    emit('update:modelValue', { ...props.modelValue, [key]: arr });
}

function removeArrayItem(key, index) {
    const arr = [...(props.modelValue[key] ?? [])];
    arr.splice(index, 1);
    emit('update:modelValue', { ...props.modelValue, [key]: arr });
}

function moveArrayItem(key, from, to) {
    const arr = [...(props.modelValue[key] ?? [])];
    const [item] = arr.splice(from, 1);
    arr.splice(to, 0, item);
    emit('update:modelValue', { ...props.modelValue, [key]: arr });
}
</script>

<template>
    <div class="space-y-5">
        <template v-for="[key, type] in fields" :key="key">

            <!-- Array repeater -->
            <div v-if="type.startsWith('array:')" class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-medium text-gray-400 uppercase tracking-wide">
                        {{ label(key) }}
                    </label>
                    <button @click="addArrayItem(key, arrayFields(type))"
                            type="button"
                            class="text-xs px-2.5 py-1 bg-indigo-600/20 text-indigo-400 rounded hover:bg-indigo-600/30 transition">
                        + Add item
                    </button>
                </div>

                <div v-if="!(modelValue[key]?.length)" class="text-xs text-gray-600 italic py-2">
                    No items yet. Click "+ Add item".
                </div>

                <div v-for="(item, index) in (modelValue[key] ?? [])" :key="index"
                     class="bg-gray-950 rounded-lg border border-gray-700 p-4 space-y-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs text-gray-600">#{{ index + 1 }}</span>
                        <div class="flex items-center gap-2">
                            <button v-if="index > 0"
                                    @click="moveArrayItem(key, index, index - 1)"
                                    type="button"
                                    class="text-xs text-gray-600 hover:text-gray-400 px-1">↑</button>
                            <button v-if="index < (modelValue[key]?.length ?? 1) - 1"
                                    @click="moveArrayItem(key, index, index + 1)"
                                    type="button"
                                    class="text-xs text-gray-600 hover:text-gray-400 px-1">↓</button>
                            <button @click="removeArrayItem(key, index)"
                                    type="button"
                                    class="text-xs text-red-600 hover:text-red-400 transition">Remove</button>
                        </div>
                    </div>
                    <div v-for="subField in arrayFields(type)" :key="subField">
                        <label class="block text-xs text-gray-500 mb-1">{{ label(subField) }}</label>
                        <input
                            :value="item[subField] ?? ''"
                            @input="setArrayItem(key, index, subField, $event.target.value)"
                            type="text"
                            class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-indigo-500"
                        />
                    </div>
                </div>
            </div>

            <!-- Textarea (text type) -->
            <div v-else-if="type === 'text'">
                <label class="block text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">
                    {{ label(key) }}
                </label>
                <textarea
                    :value="modelValue[key] ?? ''"
                    @input="setField(key, $event.target.value)"
                    rows="3"
                    class="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 resize-y focus:outline-none focus:border-indigo-500"
                />
            </div>

            <!-- Number input -->
            <div v-else-if="type === 'number'">
                <label class="block text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">
                    {{ label(key) }}
                </label>
                <input
                    :value="modelValue[key] ?? ''"
                    @input="setField(key, Number($event.target.value))"
                    type="number"
                    class="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-indigo-500"
                />
            </div>

            <!-- String input (default) -->
            <div v-else>
                <label class="block text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">
                    {{ label(key) }}
                </label>
                <input
                    :value="modelValue[key] ?? ''"
                    @input="setField(key, $event.target.value)"
                    type="text"
                    class="w-full bg-gray-950 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 focus:outline-none focus:border-indigo-500"
                />
            </div>

        </template>

        <div v-if="!fields.length" class="text-xs text-gray-600 italic">
            No editable fields for this component type.
        </div>
    </div>
</template>
