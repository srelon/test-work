const FLASH_CLASS = 'comment-item--flash'
const FLASH_DURATION_MS = 1200

export function useCommentAnchor() {
    function scroll_to_comment(id: number, block: ScrollLogicalPosition = 'center') {
        const el = document.getElementById(`comment-${id}`)
        if (!el) return

        el.scrollIntoView({ behavior: 'smooth', block })
        el.classList.add(FLASH_CLASS)
        setTimeout(() => el.classList.remove(FLASH_CLASS), FLASH_DURATION_MS)
    }

    return { scroll_to_comment }
}
