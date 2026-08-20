<template>
    <div class="mx-auto max-w-2xl px-4 py-8 sm:py-12">
        <h1 class="mb-6 text-2xl font-semibold text-neutral-900">Comments</h1>

        <CommentForm :key="form_key" @submitted="on_comment_submitted" />

        <div id="comments-section" class="mt-10">
            <CommentList
                :comments="comments"
                :new-comments="new_comments"
                :loading="is_loading"
                :current-page="pagination.current_page"
                :last-page="pagination.last_page"
                @new-comments-seen="on_new_comments_seen"
            />
        </div>

        <NewCommentsButton :count="unseen_new_comments_count" />
    </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import CommentForm from '@/components/ui/comments/CommentForm.vue'
import CommentList from '@/components/ui/comments/list/CommentList.vue'
import NewCommentsButton from '@/components/ui/comments/list/NewCommentsButton.vue'
import api from '@/plugins/axios'
import echo from '@/plugins/echo'
import { useQueryPatch } from '@/composables/useQueryPatch'
import type { Comment, Pagination } from '@/types/comment'
import { SORT_VALUES, type SortKey } from '@/types/sort'

interface CommentCreatedPayload extends Comment {
    parent_id: number | null
}

const route = useRoute()
const { patch_query } = useQueryPatch()

const comments = ref<Comment[]>([])
const new_comments = ref<Comment[]>([])
const unseen_new_comments_count = ref(0)
const is_loading = ref(true)
const form_key = ref(0)
const pagination = ref<Pagination>({
    current_page: 1,
    last_page: 1,
    total: 0,
})

const QUERY_ORDER = ['sort_by', 'page']
const COMMENTS_CHANNEL = 'comments'

function with_comment_defaults(comment: Comment): Comment {
    return { ...comment, replies: [], replies_loaded: false }
}

function sanitize_sort_by(): boolean {
    const sort_by = route.query.sort_by
    if (sort_by === undefined || SORT_VALUES.includes(String(sort_by) as SortKey)) return false

    patch_query({ sort_by: undefined }, { reset_page: false, order: QUERY_ORDER })
    return true
}

function sanitize_page() {
    const page = route.query.page
    if (page === undefined || /^\d+$/.test(String(page))) return

    patch_query({ page: undefined }, { reset_page: false, order: QUERY_ORDER })
}

function fetch_comments() {
    is_loading.value = true
    new_comments.value = []
    unseen_new_comments_count.value = 0
    api.get('comments', { params: route.query }).then(({ data }) => {
        const items = data.data.items
        comments.value = items.data.map(with_comment_defaults)
        pagination.value = items.pagination
        sanitize_page()
    }).finally(() => {
        is_loading.value = false
    })
}

function on_comment_submitted() {
    patch_query({ sort_by: undefined }, { order: QUERY_ORDER })
    form_key.value += 1
}

function on_comment_created(data: CommentCreatedPayload) {
    if (data.parent_id) {
        const parent = comments.value.find((comment) => comment.id === data.parent_id)
            ?? new_comments.value.find((comment) => comment.id === data.parent_id)
        if (! parent) return

        parent.replies_count += 1
        if (parent.replies_loaded && ! parent.replies.some((reply) => reply.id === data.id)) {
            parent.replies.push({ ...data, is_new: true })
        }
        return
    }

    if (comments.value.some((comment) => comment.id === data.id)) return
    if (new_comments.value.some((comment) => comment.id === data.id)) return
    new_comments.value.unshift(with_comment_defaults(data))
    unseen_new_comments_count.value += 1
}

function on_new_comments_seen() {
    unseen_new_comments_count.value = 0
}

watch(() => route.query, () => {
    if (sanitize_sort_by()) return
    fetch_comments()
}, { immediate: true, deep: true })

onMounted(() => {
    echo.channel(COMMENTS_CHANNEL).listen('.comment.created', on_comment_created)
})

onUnmounted(() => {
    echo.leaveChannel(COMMENTS_CHANNEL)
})
</script>
