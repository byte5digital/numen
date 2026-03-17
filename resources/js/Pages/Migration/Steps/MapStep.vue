<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    spaceId: { type: String, required: true },
    session: { type: Object, required: true },
    schema: { type: Object, default: null },
});

const emit = defineEmits(['mapped']);

const suggesting = ref(false);
const saving = ref(false);
const error = ref(null);
const suggestedMappings = ref([]);
const contentTypes = ref([]);

onMounted(async () => {
    if (props.schema?.content_types) {
        contentTypes.value = props.schema.content_types;
    }
    await suggestMappings();
});

async function suggestMappings() {
    suggesting.value = true;
    error.value = null;

    try {
        const { data } = await axios.post(
            `/api/v1/spaces/${props.spaceId}/migrations/${props.session.id}/mappings/suggest`
        );
        suggestedMappings.value = data.data ?? data;
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Failed to generate mapping suggestions';
    } finally {
        suggesting.value = false;
    }
}

function updateMapping(index, field, value) {
    suggestedMappings.value[index][field] = value;
}

function confidenceColor(confidence) {
    if (confidence >= 0.8) return 'text-emerald-400 bg-emerald-900/20';
    if (confidence >= 0.5) return 'text-amber-400 bg-amber-900/20';
    return 'text-red-400 bg-red-900/20';
}

async function acceptMappings() {
    saving.value = true;
    error.value = null;

    try {
        const { data } = await axios.put(
            `/api/v1/spaces/${props.spaceId}/migrations/${props.session.id}/mappings`,
            { mappings: suggestedMappings.value }
        );
        emit('mapped', { mappings: data.data ?? data });
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Failed to save mappings';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-semibold text-white mb-1">Map Content Types</h2>
            <p class="text-sm text-gray-500">Review AI-suggested mappings from your source CMS to Numen content types.</p>
        </div>

        <!-- Loading -->
        <div v-if="suggesting" class="flex items-center justify-center py-12">
            <div class="flex items-center gap-3 text-gray-400">
                <span class="w-5 h-5 border-2 border-gray-600 border-t-indigo-400 rounded-full animate-spin" />
                <span>AI is analyzing your content structure…</span>
            </div>
        </div>

        <!-- Error -->
        <div v-if="error" class="p-4 bg-red-900/20 border border-red-800 rounded-lg">
            <p class="text-sm text-red-400">{{ error }}</p>
        </div>

        <!-- Mappings Table -->
        <div v-if="!suggesting && suggestedMappings.length > 0" class="space-y-4">
            <div
                v-for="(mapping, index) in suggestedMappings"
                :key="index"
                class="p-4 bg-gray-800/50 border border-gray-700 rounded-lg"
            >
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-gray-200">{{ mapping.source_type }}</span>
                        <span class="text-gray-600">→</span>
                        <input
                            :value="mapping.target_type"
                            @input="updateMapping(index, 'target_type', $event.target.value)"
                            class="px-3 py-1.5 bg-gray-800 border border-gray-600 rounded text-sm text-white focus:ring-2 focus:ring-indigo-500 outline-none"
                            placeholder="Target content type"
                        />
                    </div>
                    <span
                        v-if="mapping.confidence != null"
                        class="text-xs px-2 py-1 rounded-full font-medium"
                        :class="confidenceColor(mapping.confidence)"
                    >
                        {{ Math.round(mapping.confidence * 100) }}% match
                    </span>
                </div>

                <!-- Field Mappings -->
                <div v-if="mapping.field_mappings?.length" class="ml-4 space-y-2">
                    <div
                        v-for="(field, fIndex) in mapping.field_mappings"
                        :key="fIndex"
                        class="flex items-center gap-3 text-sm"
                    >
                        <span class="text-gray-400 w-40 truncate" :title="field.source_field">{{ field.source_field }}</span>
                        <span class="text-gray-600">→</span>
                        <input
                            :value="field.target_field"
                            @input="field.target_field = $event.target.value"
                            class="px-2 py-1 bg-gray-800 border border-gray-700 rounded text-xs text-white focus:ring-1 focus:ring-indigo-500 outline-none w-40"
                        />
                        <span
                            v-if="field.confidence != null"
                            class="text-xs px-1.5 py-0.5 rounded"
                            :class="confidenceColor(field.confidence)"
                        >
                            {{ Math.round(field.confidence * 100) }}%
                        </span>
                    </div>
                </div>
            </div>

            <!-- Accept Button -->
            <div class="flex justify-end pt-4">
                <button
                    @click="acceptMappings"
                    :disabled="saving"
                    class="px-6 py-3 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition flex items-center gap-2"
                >
                    <span v-if="saving" class="flex items-center gap-2">
                        <span class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                        Saving…
                    </span>
                    <span v-else>✅ Accept Mappings</span>
                </button>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="!suggesting && suggestedMappings.length === 0 && !error" class="text-center py-12 text-gray-500">
            <p>No content types detected. Check your source connection.</p>
        </div>
    </div>
</template>
