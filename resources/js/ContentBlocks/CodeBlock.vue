<script setup>
defineProps({
    data:    { type: Object, default: () => ({}) },
    wysiwyg: { type: String, default: null },
});
</script>

<template>
    <div class="my-6 rounded-xl overflow-hidden border border-gray-700">
        <div v-if="data.language" class="flex items-center justify-between px-4 py-2 bg-gray-800 border-b border-gray-700">
            <span class="text-xs font-mono text-gray-400">{{ data.language }}</span>
        </div>
        <pre class="bg-gray-950 px-5 py-4 overflow-x-auto text-sm leading-relaxed"><code class="text-gray-200 font-mono">{{ data.code }}</code></pre>
    </div>
</template>
