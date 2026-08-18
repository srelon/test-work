<template>
    <div class="flex flex-col gap-1.5">
        <label v-if="label" class="text-sm font-medium text-neutral-700">{{ label }}</label>
        <textarea
            v-if="type === 'textarea'"
            :value="field_value"
            :name="name || undefined"
            :placeholder="placeholder"
            :rows="rows"
            class="w-full resize-y rounded-md border px-3.5 py-2.5 text-sm text-neutral-900 outline-none transition-colors placeholder:text-neutral-400 focus:border-neutral-900"
            :class="display_error ? 'border-red-400' : 'border-neutral-300'"
            @input="on_input"
            @blur="on_blur"
        ></textarea>
        <input
            v-else
            :value="field_value"
            :type="type"
            :name="name || undefined"
            :placeholder="placeholder"
            class="w-full rounded-md border px-3.5 py-2.5 text-sm text-neutral-900 outline-none transition-colors placeholder:text-neutral-400 focus:border-neutral-900"
            :class="display_error ? 'border-red-400' : 'border-neutral-300'"
            @input="on_input"
            @blur="on_blur"
        >
        <span v-if="display_error" class="text-xs text-red-500">{{ display_error }}</span>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useField } from 'vee-validate'

interface Props {
    name: string
    label?: string
    type?: string
    placeholder?: string
    rows?: number
}

const props = withDefaults(defineProps<Props>(), {
    type: 'text',
    rows: 4,
})

const { value: field_value, errorMessage, meta: field_meta, handleBlur } = useField<string>(() => props.name)

const display_error = computed(() => (field_meta.touched ? errorMessage.value : undefined))

function on_input(event: Event) {
    const target = event.target as HTMLInputElement | HTMLTextAreaElement
    field_value.value = target.value
}

function on_blur(event: Event) {
    handleBlur(event)
}
</script>
