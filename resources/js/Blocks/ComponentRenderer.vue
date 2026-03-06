<script setup>
import { defineAsyncComponent, markRaw } from 'vue';

const props = defineProps({
    component: { type: Object, required: true },
});

const blockMap = {
    hero:           markRaw(defineAsyncComponent(() => import('./HeroBlock.vue'))),
    stats_row:      markRaw(defineAsyncComponent(() => import('./StatsRow.vue'))),
    feature_grid:   markRaw(defineAsyncComponent(() => import('./FeatureGrid.vue'))),
    pipeline_steps: markRaw(defineAsyncComponent(() => import('./PipelineSteps.vue'))),
    content_list:   markRaw(defineAsyncComponent(() => import('./ContentList.vue'))),
    cta_block:      markRaw(defineAsyncComponent(() => import('./CtaBlock.vue'))),
    tech_stack:     markRaw(defineAsyncComponent(() => import('./TechStack.vue'))),
    rich_text:      markRaw(defineAsyncComponent(() => import('./RichText.vue'))),
};
</script>

<template>
    <component
        :is="blockMap[component.type]"
        v-if="blockMap[component.type]"
        :data="component.data"
        :wysiwyg="component.wysiwyg_override"
        :recent-content="component.recent_content"
    />
</template>
