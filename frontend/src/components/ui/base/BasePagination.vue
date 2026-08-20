<template>
    <nav v-if="lastPage > 1" class="flex justify-center">
        <ul class="flex flex-wrap items-center gap-1.5">
            <li>
                <button
                    type="button"
                    title="Previous page"
                    class="flex h-9 min-w-9 cursor-pointer items-center justify-center rounded-md border border-neutral-200 px-2 text-sm text-neutral-600 transition-colors hover:border-neutral-900 hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-neutral-200 disabled:hover:text-neutral-600"
                    :disabled="currentPage === 1"
                    @click="change_page(currentPage - 1)"
                >
                    &larr;
                </button>
            </li>

            <li v-if="first_visible > 1">
                <button
                    type="button"
                    class="flex h-9 min-w-9 cursor-pointer items-center justify-center rounded-md border border-neutral-200 px-2 text-sm text-neutral-600 transition-colors hover:border-neutral-900 hover:text-neutral-900"
                    @click="change_page(1)"
                >
                    1
                </button>
            </li>
            <li v-if="first_visible > 2" class="flex h-9 min-w-9 items-center justify-center text-sm text-neutral-400">&hellip;</li>

            <li v-for="page in pages" :key="page">
                <button
                    type="button"
                    class="flex h-9 min-w-9 cursor-pointer items-center justify-center rounded-md border px-2 text-sm transition-colors"
                    :class="page === currentPage ? 'border-neutral-900 bg-neutral-900 text-white' : 'border-neutral-200 text-neutral-600 hover:border-neutral-900 hover:text-neutral-900'"
                    @click="change_page(page)"
                >
                    {{ page }}
                </button>
            </li>

            <li v-if="last_visible < lastPage - 1" class="flex h-9 min-w-9 items-center justify-center text-sm text-neutral-400">&hellip;</li>
            <li v-if="last_visible < lastPage">
                <button
                    type="button"
                    class="flex h-9 min-w-9 cursor-pointer items-center justify-center rounded-md border border-neutral-200 px-2 text-sm text-neutral-600 transition-colors hover:border-neutral-900 hover:text-neutral-900"
                    @click="change_page(lastPage)"
                >
                    {{ lastPage }}
                </button>
            </li>

            <li>
                <button
                    type="button"
                    title="Next page"
                    class="flex h-9 min-w-9 cursor-pointer items-center justify-center rounded-md border border-neutral-200 px-2 text-sm text-neutral-600 transition-colors hover:border-neutral-900 hover:text-neutral-900 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-neutral-200 disabled:hover:text-neutral-600"
                    :disabled="currentPage === lastPage"
                    @click="change_page(currentPage + 1)"
                >
                    &rarr;
                </button>
            </li>
        </ul>
    </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useQueryPatch } from '@/composables/useQueryPatch'
import { usePendingScrollAnchor } from '@/composables/usePendingScrollAnchor'

interface Props {
    currentPage: number
    lastPage: number
    anchorId?: string
}

const { currentPage, lastPage, anchorId } = defineProps<Props>()

const { patch_query } = useQueryPatch()
const { queue_scroll } = usePendingScrollAnchor()

const pages = computed(() => {
    const start = Math.max(1, currentPage - 2)
    const end = Math.min(lastPage, currentPage + 2)
    const result: number[] = []
    for (let page = start; page <= end; page++) result.push(page)
    return result
})

const first_visible = computed(() => pages.value[0] ?? 0)
const last_visible = computed(() => pages.value[pages.value.length - 1] ?? 0)

function change_page(page: number) {
    if (page < 1 || page > lastPage || page === currentPage) return
    patch_query({ page: page > 1 ? String(page) : undefined }, { reset_page: false })
    if (anchorId) queue_scroll(anchorId)
}
</script>
