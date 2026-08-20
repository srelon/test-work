<template>
    <Form
        class="flex flex-col gap-4 border border-neutral-200"
        :class="variant === 'default' ? 'rounded-xl bg-white p-5 shadow-sm sm:p-6' : 'rounded-r-xl bg-neutral-50 p-4'"
        :validation-schema="schema"
        :initial-values="initial_values"
        @submit="on_submit"
    >
        <div v-if="reply_to_name" class="flex w-fit items-center gap-2 rounded-full bg-white py-1.5 pl-3 pr-2 text-xs text-neutral-700 ring-1 ring-neutral-200">
            <BaseButton variant="link" @click="on_replying_to_click">
                Replying to <strong class="font-semibold">{{ reply_to_name }}</strong>
            </BaseButton>
            <button
                type="button"
                aria-label="Clear reply target"
                class="flex h-5 w-5 cursor-pointer items-center justify-center rounded-full text-neutral-500 hover:bg-neutral-200 hover:text-neutral-900"
                @click="emit('clear-reply-to')"
            >
                &times;
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <BaseInput name="user_name" label="User Name" placeholder="JohnDoe123" />
            <BaseInput name="email" label="Email" type="email" placeholder="john@example.com" />
        </div>

        <BaseInput v-if="variant === 'default'" name="home_page" label="Home Page" placeholder="https://example.com" />

        <RichTextEditor name="text" label="Text" placeholder="Write your comment..." />

        <ImageUpload v-if="variant === 'default'" v-model="image" />

        <Recaptcha name="recaptcha_token" />

        <BaseButton type="submit" :loading="is_submitting" class="self-start">
            {{ is_submitting ? 'Sending...' : submit_label }}
        </BaseButton>
    </Form>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Form } from 'vee-validate'
import { object, string } from 'yup'
import BaseButton from '@/components/ui/base/BaseButton.vue'
import BaseInput from '@/components/ui/base/BaseInput.vue'
import Recaptcha from '@/components/ui/base/Recaptcha.vue'
import RichTextEditor from '@/components/ui/base/editor/RichTextEditor.vue'
import ImageUpload, { type CommentImage } from '@/components/ui/base/imageUpload/ImageUpload.vue'
import { useAuthorStore } from '@/stores/author'
import { useCommentAnchor } from '@/composables/useCommentAnchor'
import axios from 'axios'
import api from '@/plugins/axios'

interface Props {
    variant?: 'default' | 'reply'
    replyToName?: string
    submitLabel?: string
    parentId?: number
    repliedToCommentId?: number
}

const {
    variant = 'default',
    replyToName: reply_to_name,
    submitLabel: submit_label = 'Submit',
    parentId: parent_id,
    repliedToCommentId: replied_to_comment_id,
} = defineProps<Props>()

const emit = defineEmits<{
    'clear-reply-to': []
    submitted: []
}>()

const author_store = useAuthorStore()
const { scroll_to_comment } = useCommentAnchor()

const is_submitting = ref(false)
const image = ref<CommentImage | null>(null)

const MAX_TEXT_LENGTH = 1000

function on_replying_to_click() {
    if (replied_to_comment_id !== undefined) scroll_to_comment(replied_to_comment_id)
}

function plain_text_length(html: string | undefined): number {
    return (html ?? '').replace(/<[^>]*>/g, '').trim().length
}

const schema = object({
    user_name: string()
        .min(1, 'User Name is required')
        .max(100, 'User Name is too long')
        .matches(/^[A-Za-z0-9]+$/, 'User Name may only contain Latin letters and digits'),
    email: string().min(1, 'Email is required').email('Enter a valid email'),
    home_page: string().url('Enter a valid URL (e.g. https://example.com)'),
    text: string()
        .test('has-text', 'Comment text is required', (value) => plain_text_length(value) > 0)
        .test('max-length', `Comment text may not be greater than ${MAX_TEXT_LENGTH} characters`, (value) => plain_text_length(value) <= MAX_TEXT_LENGTH),
    recaptcha_token: string().required('Please confirm you are not a robot'),
})

interface CommentFormValues {
    user_name: string
    email: string
    home_page?: string
    text: string
    recaptcha_token: string
}

const initial_values: Partial<CommentFormValues> = {
    user_name: author_store.user_name,
    email: author_store.email,
    home_page: '',
    text: '',
}

function on_submit(form_values: Record<string, unknown>, { setErrors }: { setErrors: (fields: Record<string, string>) => void }) {
    if (is_submitting.value) return
    is_submitting.value = true

    const typed_values = form_values as unknown as CommentFormValues

    api.post('comments', {
        parent_id: parent_id,
        replied_to_comment_id: replied_to_comment_id,
        user_name: typed_values.user_name,
        email: typed_values.email,
        home_page: typed_values.home_page || undefined,
        text: typed_values.text,
        image: image.value,
        recaptcha_token: typed_values.recaptcha_token,
    }).then(() => {
        author_store.user_name = typed_values.user_name
        author_store.email = typed_values.email
        emit('submitted')
    }).catch((error) => {
        const response = axios.isAxiosError(error) ? error.response?.data?.errors : null
        if (response) {
            const formatted: Record<string, string> = {}
            Object.keys(response).forEach((field) => {
                formatted[field] = response[field][0]
            })
            setErrors(formatted)
        }
    }).finally(() => {
        is_submitting.value = false
    })
}
</script>
