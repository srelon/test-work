<template>
    <div class="mx-auto flex max-w-2xl flex-col items-center gap-4 px-4 py-24 text-center">
        <h1 class="text-4xl font-semibold text-neutral-900">404</h1>
        <p class="text-sm text-neutral-500">Page Not Found</p>
        <BaseButton @click="router.push({ name: 'home' })">
            Back to Home
        </BaseButton>
    </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import BaseButton from '@/components/ui/base/BaseButton.vue'

const router = useRouter()
</script>
