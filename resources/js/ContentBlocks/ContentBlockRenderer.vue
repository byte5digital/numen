<script setup>
/**
 * ContentBlockRenderer — dispatches content blocks to typed Vue components.
 * Falls back to DynamicBlock for AI-registered custom types not in the builtin map.
 */
import { defineAsyncComponent, markRaw, computed } from 'vue';
import DynamicBlock from './DynamicBlock.vue';

const props = defineProps({
    block:           { type: Object, required: true },
    customTypes:     { type: Object, default: () => ({}) }, // loaded from API for custom types
});

const builtinMap = {
    paragraph:  markRaw(defineAsyncComponent(() => import('./ParagraphBlock.vue'))),
    heading:    markRaw(defineAsyncComponent(() => import('./HeadingBlock.vue'))),
    code_block: markRaw(defineAsyncComponent(() => import('./CodeBlock.vue'))),
    quote:      markRaw(defineAsyncComponent(() => import('./QuoteBlock.vue'))),
    callout:    markRaw(defineAsyncComponent(() => import('./CalloutBlock.vue'))),
    divider:    markRaw(defineAsyncComponent(() => import('./DividerBlock.vue'))),
    image:      markRaw(defineAsyncComponent(() => import('./ImageBlock.vue'))),
    embed:      markRaw(defineAsyncComponent(() => import('./EmbedBlock.vue'))),
};

const resolvedComponent = computed(() =>
    builtinMap[props.block.type] ?? DynamicBlock
);

const isDynamic = computed(() => !builtinMap[props.block.type]);
</script>

<template>
    <component
        :is="resolvedComponent"
        :data="block.data"
        :wysiwyg="block.wysiwyg_override"
        :type="block.type"
        :custom-definition="isDynamic ? customTypes[block.type] : null"
    />
</template>
