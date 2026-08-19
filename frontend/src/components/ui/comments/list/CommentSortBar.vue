<template>
    <div class="flex flex-wrap items-center gap-1.5">
        <span class="text-xs font-medium text-neutral-500">Sort by</span>
        <SortButton
            v-for="field in SORT_FIELD_KEYS"
            :key="field"
            :title="SORT_FIELD_OPTIONS[field].title"
            :selects="SORT_FIELD_OPTIONS[field].selects"
            :value="sort_by"
            @select="on_select"
        />
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import SortButton from '@/components/ui/base/SortButton.vue'
import { useQueryPatch } from '@/composables/useQueryPatch'
import { SORT_FIELD_OPTIONS, type SortFieldKey } from '@/types/sort'

const SORT_FIELD_KEYS = Object.keys(SORT_FIELD_OPTIONS) as SortFieldKey[]

const route = useRoute()
const { patch_query } = useQueryPatch()

const sort_by = computed(() => (route.query.sort_by as string) ?? 'newest')

function on_select(value: string) {
    patch_query({ sort_by: value === 'newest' ? undefined : value }, { order: ['sort_by', 'page'] })
    document.getElementById('comments-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>
