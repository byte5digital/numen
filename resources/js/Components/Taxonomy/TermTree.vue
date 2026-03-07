<script setup>
import TermTreeNode from './TermTreeNode.vue';

defineProps({
    terms: { type: Array, default: () => [] },
    vocabularyId: { type: String, required: true },
    allowHierarchy: { type: Boolean, default: true },
});
</script>

<template>
    <div class="space-y-0.5">
        <TermTreeNode
            v-for="term in terms"
            :key="term.id"
            :term="term"
            :vocabulary-id="vocabularyId"
            :allow-hierarchy="allowHierarchy"
            :depth="0"
        />
    </div>
</template>
