<script setup>
import KnowledgeGraphViewer from '../../Components/Graph/KnowledgeGraphViewer.vue';

const props = defineProps({
    spaceId: { type: String, default: '' },
});
</script>

<template>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-white">Knowledge Graph</h1>
            <p class="text-gray-500 mt-1">Visualize content relationships and topic clusters</p>
        </div>

        <KnowledgeGraphViewer :space-id="spaceId" />
    </div>
</template>

<script>
import MainLayout from '../../Layouts/MainLayout.vue';
export default { layout: MainLayout };
</script>
