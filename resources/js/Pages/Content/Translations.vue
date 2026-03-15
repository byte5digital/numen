<script setup>
import { ref, computed } from 'vue';
import { Link, Head } from '@inertiajs/vue3';
import LocaleSwitcher from '../../Components/Locale/LocaleSwitcher.vue';
import TranslationEditor from '../../Components/Locale/TranslationEditor.vue';

const props = defineProps({
    content:      { type: Object, required: true },
    locales:      { type: Array,  required: true },
    translations: { type: Object, default: () => ({}) },
});

const targetLocales = computed(() =>
    props.locales.filter(l => l.code !== (props.content.locale ?? props.content.source_locale ?? 'en'))
);

const activeTab = ref(targetLocales.value[0]?.code ?? null);

function translationFor(localeCode) {
    return props.translations[localeCode] ?? null;
}

function onTranslated(localeCode, data) {
    // job dispatched — the page can reload or the parent will refresh
}

function onSaved(localeCode, data) {
    // optimistically update
}
</script>

<template>
    <Head :title="'Translations — ' + content.title" />

    <div class="min-h-screen bg-gray-950 text-gray-100">
        <!-- Header -->
        <header class="bg-gray-900 border-b border-gray-800 px-6 py-4">
            <nav class="flex items-center gap-2 text-xs text-gray-500 mb-3">
                <Link href="/content" class="hover:text-gray-300 transition-colors">Content</Link>
                <span>/</span>
                <Link :href="'/content/' + content.id" class="hover:text-gray-300 transition-colors truncate max-w-xs">
                    {{ content.title }}
                </Link>
                <span>/</span>
                <span class="text-gray-300">Translations</span>
            </nav>

            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-lg font-semibold text-gray-100 leading-tight">{{ content.title }}</h1>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Source locale:
                        <span class="font-mono text-indigo-300">
                            {{ content.locale ?? content.source_locale ?? 'en' }}
                        </span>
                    </p>
                </div>

                <LocaleSwitcher
                    :locales="locales"
                    :current-locale="activeTab"
                    :content-id="content.id"
                    @switch="activeTab = $event"
                />
            </div>
        </header>

        <!-- Body -->
        <div class="max-w-screen-xl mx-auto px-6 py-8">
            <!-- Locale tabs -->
            <div class="flex gap-1 border-b border-gray-800 mb-6 overflow-x-auto pb-px">
                <button
                    v-for="locale in targetLocales"
                    :key="locale.code"
                    @click="activeTab = locale.code"
                    :class="[
                        'px-4 py-2 text-sm font-medium rounded-t-lg whitespace-nowrap transition-colors',
                        activeTab === locale.code
                            ? 'bg-gray-800 text-indigo-300 border border-b-gray-800 border-gray-700 -mb-px'
                            : 'text-gray-500 hover:text-gray-300'
                    ]"
                >
                    {{ locale.name ?? locale.code }}
                    <span class="ml-1 font-mono text-xs opacity-60">{{ locale.code }}</span>
                </button>
            </div>

            <!-- Empty state -->
            <div v-if="targetLocales.length === 0"
                class="flex flex-col items-center justify-center py-24 gap-3 text-center">
                <span class="text-5xl">🌐</span>
                <p class="text-gray-400">No target locales configured for this space.</p>
                <Link href="/settings/locales"
                    class="text-indigo-400 hover:text-indigo-300 text-sm transition-colors">
                    Manage locales →
                </Link>
            </div>

            <!-- Active tab editor -->
            <template v-if="activeTab">
                <TranslationEditor
                    :source-content="content"
                    :target-locale="activeTab"
                    :target-content="translationFor(activeTab)"
                    @translated="(d) => onTranslated(activeTab, d)"
                    @saved="(d) => onSaved(activeTab, d)"
                />
            </template>
        </div>
    </div>
</template>
