<script setup>
import QualityConfigPanel from '../../Components/Quality/QualityConfigPanel.vue';

const props = defineProps({
    spaceId: { type: String, required: true },
    spaceName: { type: String, default: '' },
    config: { type: Object, default: null },
});
</script>

<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">Quality Scoring Settings</h1>
            <p class="mt-1 text-sm text-gray-400">
                Configure AI quality scoring for <span class="text-gray-300">{{ spaceName }}</span>
            </p>
        </div>

        <QualityConfigPanel :space-id="spaceId" :config="config" />
    </div>
</template>
