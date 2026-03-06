<script setup>
defineProps({
    data:    { type: Object, default: () => ({}) },
    wysiwyg: { type: String, default: null },
});
</script>

<template>
    <figure class="my-8">
        <div v-if="data.url" class="relative aspect-video w-full overflow-hidden rounded-xl border border-gray-800 bg-gray-900">
            <iframe
                :src="data.url"
                class="absolute inset-0 h-full w-full"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
            />
        </div>
        <figcaption v-if="data.caption" class="mt-3 text-center text-sm text-gray-500 italic">
            {{ data.caption }}
        </figcaption>
    </figure>
</template>
