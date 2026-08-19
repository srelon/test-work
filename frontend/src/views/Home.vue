<template>
    <div class="mx-auto max-w-2xl px-4 py-8 sm:py-12">
        <h1 class="mb-6 text-2xl font-semibold text-neutral-900">Comments</h1>

        <CommentForm :key="form_key" @submitted="on_comment_submitted" />

        <div id="comments-section" class="mt-10">
            <CommentList
                :comments="comments"
                :loading="is_loading"
                :current-page="pagination.current_page"
                :last-page="pagination.last_page"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import CommentForm from '@/components/ui/comments/CommentForm.vue'
import CommentList from '@/components/ui/comments/list/CommentList.vue'
import api from '@/plugins/axios'
import { useQueryPatch } from '@/composables/useQueryPatch'
import type { Comment, Pagination } from '@/types/comment'
import { SORT_VALUES, type SortKey } from '@/types/sort'

const route = useRoute()
const { patch_query } = useQueryPatch()

const comments = ref<Comment[]>([])
const is_loading = ref(true)
const form_key = ref(0)
const pagination = ref<Pagination>({
    current_page: 1,
    last_page: 1,
    total: 0,
})

const QUERY_ORDER = ['sort_by', 'page']

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
    api.get('comments', { params: route.query }).then(({ data }) => {
        const items = data.data.items
        comments.value = items.data
        pagination.value = items.pagination
        sanitize_page()
    }).finally(() => {
        is_loading.value = false
    })
}

function on_comment_submitted(comment: Comment) {
    comments.value.unshift(comment)
    form_key.value += 1
}

watch(() => route.query, () => {
    if (sanitize_sort_by()) return
    fetch_comments()
}, { immediate: true, deep: true })
</script>
