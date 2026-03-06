<script setup>
defineProps({
    data:    { type: Object, default: () => ({}) },
    wysiwyg: { type: String, default: null },
});
</script>

<template>
    <blockquote class="my-8 pl-5 border-l-4 border-indigo-500">
        <p class="text-lg text-gray-300 italic leading-relaxed">{{ data.text }}</p>
        <cite v-if="data.attribution" class="block mt-3 text-sm text-gray-500 not-italic">
            — {{ data.attribution }}
        </cite>
    </blockquote>
</template>
