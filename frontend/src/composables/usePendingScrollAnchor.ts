import { ref } from 'vue'

const pending_anchor_id = ref<string | null>(null)

export function usePendingScrollAnchor() {
    function queue_scroll(id: string) {
        pending_anchor_id.value = id
    }

    function consume_scroll(): string | null {
        const id = pending_anchor_id.value
        pending_anchor_id.value = null
        return id
    }

    return { queue_scroll, consume_scroll }
}
