<script setup>
const props = defineProps({
    formats:     { type: Array, required: true },
    modelValue:  { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const formatMeta = {
    twitter_thread: { icon: '🐦', desc: 'Thread of punchy tweets' },
    linkedin_post:  { icon: '💼', desc: 'Professional long-form post' },
    newsletter:     { icon: '📧', desc: 'Email newsletter segment' },
    instagram_caption: { icon: '📸', desc: 'Caption with hashtags' },
    podcast_script: { icon: '🎙️', desc: 'Spoken-word script' },
    product_description: { icon: '🛍️', desc: 'Sales-ready copy' },
    faq:            { icon: '❓', desc: 'Q&A format overview' },
    youtube_description: { icon: '▶️', desc: 'Video description & tags' },
};

function meta(key) {
    return formatMeta[key] ?? { icon: '📄', desc: '' };
}

function select(key) {
    emit('update:modelValue', props.modelValue === key ? null : key);
}
</script>

<template>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
        <button
            v-for="fmt in formats"
            :key="fmt.key"
            type="button"
            @click="select(fmt.key)"
            class="relative flex flex-col items-center gap-1.5 rounded-xl border p-3 text-center transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
            :class="modelValue === fmt.key
                ? 'border-indigo-500 bg-indigo-900/30 text-white'
                : 'border-gray-800 bg-gray-950/50 text-gray-400 hover:border-gray-600 hover:text-white'"
        >
            <!-- Checkmark -->
            <span
                v-if="modelValue === fmt.key"
                class="absolute top-1.5 right-1.5 text-indigo-400 text-xs leading-none"
            >✓</span>

            <!-- Icon -->
            <span class="text-2xl leading-none">{{ meta(fmt.key).icon }}</span>

            <!-- Label -->
            <span class="text-xs font-medium leading-tight">{{ fmt.label }}</span>

            <!-- Description -->
            <span class="text-[10px] text-gray-500 leading-tight">{{ meta(fmt.key).desc }}</span>
        </button>
    </div>
</template>
