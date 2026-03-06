<script setup>
defineProps({
    data:    { type: Object, default: () => ({}) },
    wysiwyg: { type: String, default: null },
});
const sizeMap = { '1': 'text-4xl', '2': 'text-3xl', '3': 'text-2xl', '4': 'text-xl' };
</script>

<template>
    <component
        :is="`h${data.level || 2}`"
        class="font-bold text-white tracking-tight mt-10 mb-4"
        :class="sizeMap[String(data.level || 2)]">
        {{ data.text }}
    </component>
</template>
