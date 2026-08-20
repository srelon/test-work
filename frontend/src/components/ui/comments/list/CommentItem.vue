<template>
    <div
        :id="loading ? undefined : `comment-${comment!.id}`"
        class="comment-item flex flex-col gap-2 border"
        :class="[card_classes, { [FLASH_CLASS]: is_flashing }]"
    >
        <template v-if="loading">
            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <BaseSkeleton width="120px" height="14px" />
                <BaseSkeleton class="ml-auto" width="70px" height="12px" />
            </div>
            <BaseSkeleton height="14px" />
            <BaseSkeleton width="90%" height="14px" />
            <BaseSkeleton width="60%" height="14px" />
        </template>

        <template v-else>
            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                <span class="text-sm font-semibold text-neutral-900">{{ comment!.user_name }}</span>
                <span class="ml-auto whitespace-nowrap text-xs text-neutral-400">{{ formatted_date }}</span>
            </div>

            <div v-if="comment!.home_page" class="text-xs text-neutral-500">
                Resource:
                <a
                    :href="comment!.home_page"
                    :title="comment!.home_page"
                    target="_blank"
                    rel="nofollow noindex noopener noreferrer"
                    class="text-blue-600 hover:underline"
                >
                    {{ truncated_home_page }}
                </a>
            </div>

            <BaseButton
                v-if="is_reply && comment!.replied_to"
                variant="text"
                class="w-fit text-xs text-neutral-500"
                @click="scroll_to_comment(comment!.replied_to!.id)"
            >
                ↳ Replying to {{ comment!.replied_to!.user_name }}
            </BaseButton>

            <button
                v-if="comment!.image"
                type="button"
                title="View image"
                class="block w-fit cursor-zoom-in"
                @click="lightbox_open = true"
            >
                <img
                    :src="comment!.image.cropped"
                    alt="Comment attachment"
                    class="max-w-60 rounded-md border border-neutral-200 object-cover"
                >
            </button>

            <ImageLightbox v-if="comment!.image" v-model:open="lightbox_open" :src="comment!.image.original" alt="Comment attachment" />

            <div class="comment-text whitespace-pre-line text-sm leading-relaxed text-neutral-800" v-html="sanitized_text"></div>

            <div class="flex items-center gap-2">
                <BaseButton variant="accent" @click="on_reply_click">
                    Reply
                </BaseButton>
            </div>

            <CommentReplies v-if="!is_reply" ref="replies_ref" :comment="comment!" />
        </template>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import BaseButton from '@/components/ui/base/BaseButton.vue'
import BaseSkeleton from '@/components/ui/base/BaseSkeleton.vue'
import ImageLightbox from '@/components/ui/base/ImageLightbox.vue'
import CommentReplies from './CommentReplies.vue'
import { sanitize_comment_html } from '@/utils/sanitizeCommentHtml'
import { FLASH_CLASS, FLASH_DURATION_MS, useCommentAnchor } from '@/composables/useCommentAnchor'
import type { Comment } from '@/types/comment'

const MAX_HOME_PAGE_LENGTH = 50

interface Props {
    comment?: Comment
    is_reply?: boolean
    is_new?: boolean
    loading?: boolean
}

const { comment, is_reply = false, is_new = false, loading = false } = defineProps<Props>()

const emit = defineEmits<{
    'reply-to-me': []
}>()

const { scroll_to_comment } = useCommentAnchor()

const lightbox_open = ref(false)
const replies_ref = ref<InstanceType<typeof CommentReplies> | null>(null)
const is_flashing = ref(is_new)

const card_classes = computed(() => {
    if (is_reply) return 'comment-item--reply rounded-r-xl border-neutral-200 bg-neutral-50 p-4'
    return 'rounded-xl border-neutral-200 bg-white p-5 shadow-sm sm:p-6'
})

const sanitized_text = computed(() => sanitize_comment_html(comment!.text))

const truncated_home_page = computed(() => {
    const url = comment!.home_page ?? ''
    return url.length > MAX_HOME_PAGE_LENGTH ? `${url.slice(0, MAX_HOME_PAGE_LENGTH)}...` : url
})

const formatted_date = computed(() => new Date(comment!.created_at).toLocaleString(undefined, {
    hour: '2-digit',
    minute: '2-digit',
    month: 'short',
    day: 'numeric',
}))

onMounted(() => {
    if (! is_new) return
    setTimeout(() => { is_flashing.value = false }, FLASH_DURATION_MS)
})

function on_reply_click() {
    if (is_reply) {
        emit('reply-to-me')
        return
    }

    replies_ref.value?.open()
}
</script>

<style scoped>
.comment-item--flash {
    animation: comment-item-flash 1.2s ease;
}

.comment-text :deep(a) {
    color: #2563eb;
    text-decoration: underline;
}

.comment-text :deep(code) {
    display: block;
    overflow-x: auto;
    border-radius: 0.375rem;
    background-color: #2b2b2b;
    color: #ffc66d;
    padding: 0.5rem 0.75rem;
    margin: 0.25rem 0;
    font-family: ui-monospace, monospace;
    font-size: 0.85em;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-word;
}

@keyframes comment-item-flash {
    0%, 100% {
        background-color: transparent;
    }
    30% {
        background-color: rgba(37, 99, 235, 0.08);
    }
}
</style>
