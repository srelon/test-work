<template>
    <div class="flex flex-col gap-6">
        <CommentSortBar />

        <p v-if="loading" class="rounded-xl border border-neutral-200 bg-white p-5 text-sm text-neutral-400 shadow-sm">Loading comments...</p>

        <template v-else>
            <div v-if="comments.length" class="flex flex-col gap-6">
                <CommentItem v-for="comment in comments" :key="comment.id" :comment="comment" />
            </div>
            <p v-else class="rounded-xl border border-neutral-200 bg-white p-5 text-sm text-neutral-400 shadow-sm">No comments yet.</p>

            <BasePagination :current-page="currentPage" :last-page="lastPage" />
        </template>
    </div>
</template>

<script setup lang="ts">
import BasePagination from '@/components/ui/base/BasePagination.vue'
import CommentSortBar from './CommentSortBar.vue'
import CommentItem from './CommentItem.vue'
import type { Comment } from '@/types/comment'

interface Props {
    comments: Comment[]
    loading?: boolean
    currentPage: number
    lastPage: number
}

defineProps<Props>()
</script>
