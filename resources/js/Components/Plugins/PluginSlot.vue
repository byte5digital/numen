<script setup>
import { computed } from 'vue';
import { pluginRegistry } from '@/plugins/registry.js';

const props = defineProps({
    /**
     * Slot identifier — must match one of the PLUGIN_SLOTS constants.
     * e.g. 'admin.sidebar', 'admin.dashboard.widget'
     */
    slot: {
        type: String,
        required: true,
    },
    /**
     * Props forwarded to every component rendered inside this slot.
     */
    slotProps: {
        type: Object,
        default: () => ({}),
    },
});

const components = computed(() => pluginRegistry.getSlotComponents(props.slot));
</script>

<template>
    <template v-if="components.length">
        <component
            v-for="(comp, index) in components"
            :key="index"
            :is="comp"
            v-bind="slotProps"
        />
    </template>
</template>
