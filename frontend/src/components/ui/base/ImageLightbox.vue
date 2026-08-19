<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex cursor-pointer items-center justify-center bg-black/85 p-6" @click="emit('update:open', false)">
            <img :src="src" :alt="alt" class="max-h-[calc(100vh-96px)] max-w-full cursor-pointer select-none rounded-lg object-contain">
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
