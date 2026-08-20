<template>
    <BaseButton
        v-if="show_replies || comment.replies_count > 0"
        variant="text"
        class="w-full justify-center border-t border-neutral-200 pt-3 text-center"
        @click="show_replies = !show_replies"
    >
        {{ show_replies ? 'Hide replies' : `Show replies (${comment.replies_count})` }}
    </BaseButton>

    <div v-if="show_replies" class="flex items-stretch">
        <button
            type="button"
            title="Back to comment"
            class="-ml-5 flex w-11 flex-shrink-0 cursor-pointer items-end justify-center pb-3 text-blue-400 hover:bg-blue-50 hover:text-blue-600 sm:-ml-6 sm:w-12"
            @click="scroll_to_comment(comment.id, 'start')"
        >
            ↑
        </button>

        <div class="flex flex-1 flex-col gap-4">
            <template v-if="is_loading_replies">
                <CommentItem v-for="n in comment.replies_count" :key="n" loading is_reply />
            </template>

            <TransitionGroup name="new-comment" tag="div" class="flex flex-col gap-4">
                <CommentItem
                    v-for="reply in comment.replies"
                    :key="reply.id"
                    :comment="reply"
                    is_reply
                    :is_new="reply.is_new"
                    @reply-to-me="on_reply_to_me(reply)"
                />
            </TransitionGroup>

            <div ref="compose_wrap">
                <CommentForm
                    :key="reply_form_key"
                    variant="reply"
                    :parent-id="comment.id"
                    :replied-to-comment-id="replying_to?.id"
                    :reply-to-name="replying_to?.user_name"
                    submit-label="Post Reply"
                    @clear-reply-to="replying_to = null"
                    @submitted="on_reply_submitted"
                />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import BaseButton from '@/components/ui/base/BaseButton.vue'
import CommentForm from '@/components/ui/comments/CommentForm.vue'
import CommentItem from './CommentItem.vue'
import api from '@/plugins/axios'
import { useCommentAnchor } from '@/composables/useCommentAnchor'
import type { Comment } from '@/types/comment'

interface Props {
    comment: Comment
}

const props = defineProps<Props>()

const { scroll_to_comment } = useCommentAnchor()

const show_replies = ref(false)
const is_loading_replies = ref(false)
const compose_wrap = ref<HTMLElement | null>(null)
const replying_to = ref<Comment | null>(null)
const reply_form_key = ref(0)

function scroll_to_compose() {
    nextTick(() => compose_wrap.value?.scrollIntoView({ behavior: 'smooth', block: 'center' }))
}

function fetch_replies() {
    if (props.comment.replies_loaded) return

    is_loading_replies.value = true
    api.get<{ data: { replies: Comment[] } }>(`comments/${props.comment.id}/replies`).then(({ data }) => {
        props.comment.replies = data.data.replies
        props.comment.replies_loaded = true
    }).finally(() => {
        is_loading_replies.value = false
    })
}

watch(show_replies, (open) => {
    if (open) fetch_replies()
})

function on_reply_to_me(reply: Comment) {
    replying_to.value = reply
    show_replies.value = true
    scroll_to_compose()
}

function on_reply_submitted() {
    replying_to.value = null
    reply_form_key.value += 1
}

function open() {
    show_replies.value = true
    scroll_to_compose()
}

defineExpose({ open })
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
