<template>
    <button
        v-if="is_scrolled"
        type="button"
        title="Scroll to top"
        class="fixed bottom-6 right-3 z-40 flex h-12 w-12 cursor-pointer items-center justify-center rounded-full border border-blue-200 bg-blue-50 text-blue-700 opacity-70 shadow-lg transition-[transform,opacity] hover:scale-105 hover:bg-blue-100 hover:opacity-100"
        @click="scroll_up"
    >
        <span class="text-lg leading-none">&uarr;</span>
        <span
            v-if="count > 0"
            class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-xs font-semibold text-white"
        >
            {{ count }}
        </span>
    </button>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'

interface Props {
    count: number
}

defineProps<Props>()

const SCROLL_THRESHOLD = 200

const is_scrolled = ref(false)

function update_scrolled_state() {
    is_scrolled.value = window.scrollY > SCROLL_THRESHOLD
}

function scroll_up() {
    const new_comments_section = document.getElementById('new-comments-section')
    if (new_comments_section) {
        new_comments_section.scrollIntoView({ behavior: 'smooth', block: 'start' })
        return
    }

    window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(() => {
    update_scrolled_state()
    window.addEventListener('scroll', update_scrolled_state, { passive: true })
})

onUnmounted(() => {
    window.removeEventListener('scroll', update_scrolled_state)
})
</script>
