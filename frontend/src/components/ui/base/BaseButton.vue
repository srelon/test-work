<template>
    <button
        class="inline-flex cursor-pointer items-center justify-center rounded-md transition-colors disabled:pointer-events-none disabled:opacity-50"
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
    variant?: 'primary' | 'outline' | 'text' | 'chip' | 'accent' | 'link'
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
    primary: 'gap-2 px-5 py-2.5 text-sm font-semibold bg-neutral-900 text-white hover:bg-neutral-700',
    outline: 'gap-2 px-5 py-2.5 text-sm font-semibold border border-neutral-300 text-neutral-900 hover:border-neutral-900',
    text: 'gap-2 p-0 text-sm font-semibold text-neutral-900 hover:text-neutral-600',
    chip: 'gap-2 rounded-full bg-neutral-100 px-3 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-200',
    accent: 'gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100',
    link: 'gap-0 p-0 text-xs font-normal text-neutral-700 hover:text-blue-600',
}
</script>
