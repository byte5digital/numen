<script setup>
defineProps({
    data:    { type: Object, default: () => ({}) },
    wysiwyg: { type: String, default: null },
});
</script>

<template>
    <section class="py-12 px-6 bg-white">
        <div class="max-w-4xl mx-auto prose prose-lg max-w-none prose-byte
                    prose-headings:text-slate-900 prose-headings:font-bold
                    prose-p:text-slate-700 prose-p:leading-relaxed
                    prose-code:text-indigo-500 prose-code:bg-slate-50 prose-code:px-1 prose-code:rounded
                    prose-pre:bg-slate-50 prose-pre:border prose-pre:border-slate-200
                    prose-strong:text-slate-900 prose-a:text-indigo-500
                    prose-li:text-slate-700"
             v-html="wysiwyg || data.content || ''" />
    </section>
</template>
