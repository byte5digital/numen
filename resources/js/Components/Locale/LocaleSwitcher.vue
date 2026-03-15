<script setup>
import { ref, computed } from 'vue';
import LocaleStatusBadge from './LocaleStatusBadge.vue';

const props = defineProps({
    // Array of SpaceLocale objects: { locale, label, flag, is_default, is_active }
    locales:       { type: Array, required: true },
    currentLocale: { type: String, required: true },
    // If provided, show per-locale translation status and "Translate to X" actions
    contentId:     { type: String, default: null },
    // Map of locale → translation status for the current content
    translations:  { type: Object, default: () => ({}) },
});

const emit = defineEmits(['switch', 'translate']);

const open = ref(false);

const current = computed(() =>
    props.locales.find(l => l.locale === props.currentLocale) ?? {
        locale: props.currentLocale,
        label: props.currentLocale,
        flag: '🌐',
    }
);

const others = computed(() =>
    props.locales.filter(l => l.locale !== props.currentLocale && l.is_active !== false)
);

function select(locale) {
    open.value = false;
    if (locale.locale === props.currentLocale) return;
    emit('switch', locale);
}

function triggerTranslate(locale, event) {
    event.stopPropagation();
    open.value = false;
    emit('translate', locale);
}

function close() {
    open.value = false;
}

function statusFor(locale) {
    return props.translations[locale] ?? null;
}
</script>

<template>
    <div class="relative inline-block text-left" v-click-outside="close">
        <!-- Trigger button -->
        <button
            type="button"
            @click="open = !open"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-lg
                   bg-gray-800/60 border border-gray-700/40 text-gray-200
                   hover:bg-gray-700/60 hover:border-gray-600/60 transition-colors"
        >
            <span class="text-base leading-none">{{ current.flag ?? '🌐' }}</span>
            <span class="font-medium">{{ current.label ?? current.locale }}</span>
            <svg class="w-3.5 h-3.5 text-gray-400 transition-transform" :class="{ 'rotate-180': open }"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        <!-- Dropdown -->
        <transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="absolute z-50 mt-1 min-w-[200px] rounded-lg shadow-lg
                       bg-gray-900 border border-gray-700/60 py-1 origin-top-left"
            >
                <!-- Current locale header -->
                <div class="px-3 py-1.5 text-xs text-gray-500 uppercase tracking-wide border-b border-gray-800">
                    Current language
                </div>
                <div class="px-3 py-2 flex items-center gap-2 bg-gray-800/40">
                    <span class="text-base">{{ current.flag ?? '🌐' }}</span>
                    <span class="text-sm text-gray-200 font-medium flex-1">{{ current.label ?? current.locale }}</span>
                    <span v-if="current.is_default" class="text-xs text-indigo-400">Default</span>
                </div>

                <!-- Other locales -->
                <template v-if="others.length">
                    <div class="px-3 py-1.5 text-xs text-gray-500 uppercase tracking-wide border-t border-gray-800 mt-1">
                        Switch to
                    </div>
                    <div
                        v-for="locale in others"
                        :key="locale.locale"
                        class="group flex items-center gap-2 px-3 py-2 cursor-pointer
                               hover:bg-gray-800/60 transition-colors"
                        @click="select(locale)"
                    >
                        <span class="text-base">{{ locale.flag ?? '🌐' }}</span>
                        <span class="text-sm text-gray-200 flex-1">{{ locale.label ?? locale.locale }}</span>

                        <!-- Translation status badge (content mode) -->
                        <template v-if="contentId">
                            <LocaleStatusBadge :status="statusFor(locale.locale)" class="text-[10px]" />
                            <button
                                v-if="statusFor(locale.locale) !== 'completed'"
                                type="button"
                                @click="triggerTranslate(locale, $event)"
                                class="ml-1 text-xs text-indigo-400 hover:text-indigo-200 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap"
                                :title="`Translate to ${locale.label ?? locale.locale}`"
                            >
                                Translate →
                            </button>
                        </template>
                    </div>
                </template>

                <div v-else class="px-3 py-2 text-xs text-gray-500 italic">
                    No other active locales
                </div>
            </div>
        </transition>
    </div>
</template>
