<template>
    <button
        type="button"
        class="flex cursor-pointer items-center gap-1 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
        :class="is_active ? 'border-neutral-900 bg-neutral-900 text-white' : 'border-neutral-200 bg-white text-neutral-600 hover:border-neutral-900 hover:text-neutral-900'"
        @click="emit('select', target_value)"
    >
        {{ title }}
        <svg
            v-if="is_active"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            class="h-3 w-3 transition-transform"
            :class="value === selects[1] ? 'rotate-180' : ''"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m0 0l-6-6m6 6l6-6" />
        </svg>
    </button>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { SortOption } from '@/types/sort'

interface Props extends SortOption {
    value: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
    select: [value: string]
}>()

const is_active = computed(() => props.selects.includes(props.value))

const target_value = computed(() => (props.value === props.selects[0] ? props.selects[1] : props.selects[0]))
</script>
