<template>
    <div class="flex flex-col gap-6">
        <CommentSortBar />

        <template v-if="loading">
            <CommentItem v-for="n in skeleton_count" :key="n" loading />
        </template>

        <template v-else>
            <div v-if="newComments.length" id="new-comments-section" ref="new_comments_ref" class="flex flex-col gap-6">
                <div class="flex items-center gap-3">
                    <span class="h-0.5 flex-1 rounded-full bg-blue-300"></span>
                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700">
                        New comments ({{ newComments.length }})
                    </span>
                    <span class="h-0.5 flex-1 rounded-full bg-blue-300"></span>
                </div>

                <TransitionGroup name="new-comment" tag="div" class="flex flex-col gap-6">
                    <CommentItem v-for="comment in newComments" :key="comment.id" :comment="comment" is_new />
                </TransitionGroup>

                <span class="h-0.5 w-full rounded-full bg-blue-300"></span>
            </div>

            <div v-if="comments.length" class="flex flex-col gap-6">
                <CommentItem v-for="comment in comments" :key="comment.id" :comment="comment" />
            </div>
            <p v-else class="rounded-xl border border-neutral-200 bg-white p-5 text-sm text-neutral-400 shadow-sm">No comments yet.</p>

            <BasePagination :current-page="currentPage" :last-page="lastPage" anchor-id="comment-sort-bar" />
        </template>
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import BasePagination from '@/components/ui/base/BasePagination.vue'
import CommentSortBar from './CommentSortBar.vue'
import CommentItem from './CommentItem.vue'
import type { Comment } from '@/types/comment'

interface Props {
    comments: Comment[]
    newComments: Comment[]
    loading?: boolean
    currentPage: number
    lastPage: number
}

const FALLBACK_SKELETON_COUNT = 5

const props = defineProps<Props>()

const skeleton_count = computed(() => props.comments.length || FALLBACK_SKELETON_COUNT)

const emit = defineEmits<{
    'new-comments-seen': []
}>()

const new_comments_ref = ref<HTMLElement | null>(null)
let observer: IntersectionObserver | null = null

function disconnect_observer() {
    observer?.disconnect()
    observer = null
}

watch(() => props.newComments.length, async (length) => {
    disconnect_observer()
    if (length === 0) return

    await nextTick()
    if (! new_comments_ref.value) return

    observer = new IntersectionObserver((entries) => {
        if (entries[0]?.isIntersecting) {
            emit('new-comments-seen')
            disconnect_observer()
        }
    })
    observer.observe(new_comments_ref.value)
})

onBeforeUnmount(disconnect_observer)
</script>

<style scoped>
.new-comment-enter-active,
.new-comment-leave-active {
    transition: opacity 0.4s ease, transform 0.4s ease;
}

.new-comment-enter-from {
    opacity: 0;
    transform: translateY(-12px);
}

.new-comment-leave-to {
    opacity: 0;
}
</style>
