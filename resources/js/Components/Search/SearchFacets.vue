<template>
  <div class="numen-search-facets space-y-4">
    <!-- Content Type filter -->
    <div v-if="facets.content_types?.length">
      <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Content Type</h4>
      <ul class="space-y-1">
        <li v-for="ct in facets.content_types" :key="ct.slug">
          <label class="flex cursor-pointer items-center gap-2 text-sm">
            <input
              type="checkbox"
              :value="ct.slug"
              :checked="selectedTypes.includes(ct.slug)"
              class="rounded border-gray-300 text-indigo-600"
              @change="toggleType(ct.slug)"
            />
            <span class="flex-1">{{ ct.name }}</span>
            <span class="text-xs text-gray-400">{{ ct.count }}</span>
          </label>
        </li>
      </ul>
    </div>

    <!-- Locale filter -->
    <div v-if="facets.locales?.length">
      <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Language</h4>
      <ul class="space-y-1">
        <li v-for="locale in facets.locales" :key="locale.code">
          <label class="flex cursor-pointer items-center gap-2 text-sm">
            <input
              type="radio"
              name="locale"
              :value="locale.code"
              :checked="selectedLocale === locale.code"
              class="border-gray-300 text-indigo-600"
              @change="emit('locale', locale.code)"
            />
            <span>{{ locale.label }}</span>
          </label>
        </li>
      </ul>
    </div>

    <!-- Reset -->
    <button
      v-if="hasActiveFilters"
      class="text-xs text-indigo-600 hover:underline"
      @click="reset"
    >
      Clear all filters
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface ContentTypeFacet {
  slug: string
  name: string
  count: number
}

interface LocaleFacet {
  code: string
  label: string
}

interface Facets {
  content_types?: ContentTypeFacet[]
  locales?: LocaleFacet[]
}

const props = defineProps<{
  facets: Facets
  selectedTypes: string[]
  selectedLocale?: string
}>()

const emit = defineEmits<{
  'update:selectedTypes': [types: string[]]
  locale: [locale: string]
  reset: []
}>()

const hasActiveFilters = computed(() =>
  props.selectedTypes.length > 0 || !!props.selectedLocale
)

function toggleType(slug: string) {
  const current = [...props.selectedTypes]
  const idx = current.indexOf(slug)
  if (idx >= 0) {
    current.splice(idx, 1)
  } else {
    current.push(slug)
  }
  emit('update:selectedTypes', current)
}

function reset() {
  emit('reset')
}
</script>
