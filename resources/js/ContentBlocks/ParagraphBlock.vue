<script setup>
import { marked } from 'marked';
import { computed } from 'vue';
const props = defineProps({
    data:    { type: Object, default: () => ({}) },
    wysiwyg: { type: String, default: null },
});
const html = computed(() => {
    const src = props.wysiwyg || props.data?.text || '';
    // if it looks like HTML already, use as-is; otherwise parse markdown
    return src.trimStart().startsWith('<') ? src : marked.parse(src);
});
</script>

<template>
    <div class="prose prose-invert max-w-none
                prose-p:text-gray-300 prose-p:leading-relaxed prose-p:my-4
                prose-strong:text-white prose-a:text-indigo-500 prose-a:no-underline hover:prose-a:underline
                prose-li:text-gray-300 prose-ul:my-4 prose-ol:my-4"
         v-html="html" />
</template>
