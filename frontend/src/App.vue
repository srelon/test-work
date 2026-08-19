<template>
    <div class="min-h-screen bg-neutral-50">
        <router-view />
    </div>

    <ExternalLinkConfirm v-if="confirm_url" :url="confirm_url" @close="confirm_url = null" />
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import ExternalLinkConfirm from '@/components/ui/base/ExternalLinkConfirm.vue'

const confirm_url = ref<string | null>(null)

function on_document_click(event: MouseEvent) {
    const link = (event.target as HTMLElement).closest('a')
    if (!link) return

    const href = link.getAttribute('href') ?? ''
    if (!/^https?:\/\//i.test(href)) return

    try {
        if (new URL(href).host === window.location.host) return
    } catch {
        return
    }

    event.preventDefault()
    confirm_url.value = href
}

onMounted(() => document.addEventListener('click', on_document_click))
onUnmounted(() => document.removeEventListener('click', on_document_click))
</script>
