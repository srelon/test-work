import { useRoute, useRouter } from 'vue-router'
import type { LocationQueryRaw } from 'vue-router'

function order_query(query: LocationQueryRaw, order: string[]): LocationQueryRaw {
    const ordered: LocationQueryRaw = {}
    for (const key of order) {
        if (key in query) ordered[key] = query[key]
    }
    for (const key of Object.keys(query)) {
        if (!(key in ordered)) ordered[key] = query[key]
    }
    return ordered
}

export function useQueryPatch() {
    const route = useRoute()
    const router = useRouter()

    function patch_query(patch: LocationQueryRaw, options: { reset_page?: boolean, order?: string[] } = {}) {
        const reset_page = options.reset_page ?? true

        const merged: LocationQueryRaw = {
            ...route.query,
            ...(reset_page ? { page: undefined } : {}),
            ...patch,
        }

        router.replace({
            query: options.order ? order_query(merged, options.order) : merged,
        })
    }

    return { patch_query }
}
