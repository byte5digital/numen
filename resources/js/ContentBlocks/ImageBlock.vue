<script setup>
defineProps({
    data:    { type: Object, default: () => ({}) },
    wysiwyg: { type: String, default: null },
});
</script>

<template>
    <figure class="my-8">
        <img v-if="data.url" :src="data.url" :alt="data.alt || ''"
             class="w-full rounded-xl border border-gray-800 object-cover" />
        <figcaption v-if="data.caption" class="mt-3 text-center text-sm text-gray-500 italic">
            {{ data.caption }}
        </figcaption>
    </figure>
</template>
