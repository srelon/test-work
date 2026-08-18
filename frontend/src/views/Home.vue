<template>
    <div class="mx-auto max-w-2xl px-4 py-8 sm:py-12">
        <h1 class="mb-6 text-2xl font-semibold text-neutral-900">Comments</h1>

        <CommentForm />

        <div class="mt-10 border-t border-neutral-200 pt-6 text-sm text-neutral-400">
            Comments list coming soon.
        </div>
    </div>
</template>

<script setup lang="ts">
import CommentForm from '@/components/ui/comments/CommentForm.vue'
</script>
