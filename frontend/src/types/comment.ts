import type { CommentImage } from '@/components/ui/base/imageUpload/ImageUpload.vue'

export interface CommentRepliedTo {
    id: number
    user_name: string
}

export interface Pagination {
    current_page: number
    last_page: number
    total: number
}

export interface Comment {
    id: number
    user_name: string
    email: string
    home_page?: string
    text: string
    image?: CommentImage
    created_at: string
    replied_to?: CommentRepliedTo
    replies: Comment[]
    replies_count: number
    replies_loaded: boolean
}
