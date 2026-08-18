<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex cursor-pointer items-center justify-center bg-black/85 p-6" @click="emit('update:open', false)">
            <button
                type="button"
                aria-label="Close"
                class="absolute right-4 top-4 z-10 flex h-11 w-11 cursor-pointer items-center justify-center rounded-full bg-white/15 text-white transition-colors hover:bg-white/30"
                @click.stop="emit('update:open', false)"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 6L6 18M6 6l12 12" />
                </svg>
            </button>

            <img :src="src" :alt="alt" class="max-h-[calc(100vh-96px)] max-w-full cursor-default select-none rounded-lg object-contain" @click.stop>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, watch } from 'vue'

interface Props {
    src: string
    open: boolean
    alt?: string
}

const props = withDefaults(defineProps<Props>(), {
    alt: '',
})

const emit = defineEmits<{
    'update:open': [value: boolean]
}>()

function on_keydown(event: KeyboardEvent) {
    if (props.open && event.key === 'Escape') emit('update:open', false)
}

watch(() => props.open, (val) => {
    document.body.style.overflow = val ? 'hidden' : ''
})

onMounted(() => window.addEventListener('keydown', on_keydown))
onUnmounted(() => window.removeEventListener('keydown', on_keydown))
</script>
