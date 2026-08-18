<template>
    <form class="flex flex-col gap-4" @submit="on_form_submit">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <BaseInput name="user_name" label="User Name" placeholder="John Doe" />
            <BaseInput name="email" label="Email" type="email" placeholder="john@example.com" />
        </div>

        <BaseInput name="home_page" label="Home Page" placeholder="https://example.com" />

        <CommentEditor name="text" label="Text" placeholder="Write your comment..." />

        <ImageUpload v-model="image" />

        <BaseButton type="submit" :loading="is_submitting" class="self-start">
            {{ is_submitting ? 'Sending...' : 'Submit' }}
        </BaseButton>
    </form>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useForm } from 'vee-validate'
import { object, string } from 'yup'
import BaseButton from '@/components/ui/base/BaseButton.vue'
import BaseInput from '@/components/ui/base/BaseInput.vue'
import CommentEditor from '@/components/ui/comments/editor/CommentEditor.vue'
import ImageUpload, { type CommentImage } from '@/components/ui/comments/imageUpload/ImageUpload.vue'
import { useAuthorStore } from '@/stores/author'

const author_store = useAuthorStore()

const is_submitting = ref(false)
const image = ref<CommentImage | null>(null)

function plain_text_length(html: string | undefined): number {
    return (html ?? '').replace(/<[^>]*>/g, '').trim().length
}

// Well-formed [code] pairs become real tags; leftover/malformed brackets are stripped.
function sanitize_code_markers(html: string): string {
    return html
        .replace(/\[code\]([\s\S]*?)\[\/code\]/g, '<code>$1</code>')
        .replace(/\[[^\]]*\]/g, '')
}

const schema = object({
    user_name: string().min(1, 'User Name is required').max(100, 'User Name is too long'),
    email: string().min(1, 'Email is required').email('Enter a valid email'),
    home_page: string().url('Enter a valid URL (e.g. https://example.com)'),
    text: string().test('has-text', 'Comment text is required', (value) => plain_text_length(value) > 0),
})

interface CommentFormValues {
    user_name: string
    email: string
    home_page?: string
    text: string
}

const { handleSubmit, values } = useForm({
    validationSchema: schema,
    initialValues: {
        user_name: author_store.user_name,
        email: author_store.email,
        home_page: '',
        text: '',
    },
})

watch(() => values.user_name, (val) => {
    if (typeof val === 'string') author_store.user_name = val
})

watch(() => values.email, (val) => {
    if (typeof val === 'string') author_store.email = val
})

const on_form_submit = handleSubmit(async (form_values) => {
    const typed_values = form_values as CommentFormValues
    typed_values.text = sanitize_code_markers(typed_values.text)
    is_submitting.value = true
    try {
        await new Promise((resolve) => setTimeout(resolve, 800))
        console.log('Comment submitted:', { ...typed_values, image: image.value })
    } finally {
        is_submitting.value = false
    }
})
</script>
