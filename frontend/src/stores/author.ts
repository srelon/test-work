import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

const STORAGE_KEY = 'comments:author'

interface SavedAuthor {
    user_name: string
    email: string
}

function load(): SavedAuthor {
    try {
        const raw = localStorage.getItem(STORAGE_KEY)
        if (!raw) return { user_name: '', email: '' }
        const parsed = JSON.parse(raw)
        return {
            user_name: typeof parsed.user_name === 'string' ? parsed.user_name : '',
            email: typeof parsed.email === 'string' ? parsed.email : '',
        }
    } catch {
        return { user_name: '', email: '' }
    }
}

function save(author: SavedAuthor) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(author))
    } catch {}
}

export const useAuthorStore = defineStore('author', () => {
    const saved = load()
    const user_name = ref(saved.user_name)
    const email = ref(saved.email)

    watch([user_name, email], () => save({ user_name: user_name.value, email: email.value }))

    return { user_name, email }
})
