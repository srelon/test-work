<template>
    <button
        class="inline-flex items-center justify-center gap-2 rounded-md px-5 py-2.5 text-sm font-semibold transition-colors disabled:pointer-events-none disabled:opacity-50"
        :class="variant_classes[variant]"
        :type="type"
        :disabled="disabled || loading"
    >
        <span
            v-if="loading"
            class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
            aria-hidden="true"
        ></span>
        <slot v-else />
    </button>
</template>

<script setup lang="ts">
interface Props {
    variant?: 'primary' | 'outline' | 'text'
    type?: 'button' | 'submit' | 'reset'
    disabled?: boolean
    loading?: boolean
}

withDefaults(defineProps<Props>(), {
    variant: 'primary',
    type: 'button',
    disabled: false,
    loading: false,
})

const variant_classes: Record<NonNullable<Props['variant']>, string> = {
    primary: 'bg-neutral-900 text-white hover:bg-neutral-700',
    outline: 'border border-neutral-300 text-neutral-900 hover:border-neutral-900',
    text: 'p-0 text-neutral-900 hover:text-neutral-600',
}
</script>
