<template>
    <div v-if="is_open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="close">
        <div class="flex w-full max-w-sm flex-col gap-3 rounded-lg bg-white p-4">
            <h3 class="text-sm font-semibold text-neutral-900">{{ is_editing_link ? 'Edit Link' : 'Insert Link' }}</h3>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-neutral-600">Link Text</label>
                <input
                    v-model="link_text"
                    type="text"
                    placeholder="Text to display"
                    class="w-full rounded border border-neutral-300 px-2.5 py-1.5 text-sm outline-none focus:border-neutral-900"
                >
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-neutral-600">URL</label>
                <input
                    v-model="link_url"
                    type="text"
                    placeholder="https://example.com"
                    class="w-full rounded border px-2.5 py-1.5 text-sm outline-none"
                    :class="url_error ? 'border-red-400' : 'border-neutral-300 focus:border-neutral-900'"
                    @blur="url_touched = true"
                >
                <span v-if="url_error" class="text-xs text-red-500">{{ url_error }}</span>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-neutral-600">Title (optional)</label>
                <input
                    v-model="link_title"
                    type="text"
                    placeholder="Link title"
                    class="w-full rounded border border-neutral-300 px-2.5 py-1.5 text-sm outline-none focus:border-neutral-900"
                >
            </div>

            <div class="mt-1 flex justify-end gap-2">
                <BaseButton v-if="is_editing_link" variant="outline" @click="remove_link">Remove</BaseButton>
                <BaseButton variant="outline" @click="close">Cancel</BaseButton>
                <BaseButton variant="primary" @click="apply_link">{{ is_editing_link ? 'Update' : 'Insert' }}</BaseButton>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Editor } from '@tiptap/vue-3'
import type { Node as ProseMirrorNode } from '@tiptap/pm/model'
import BaseButton from '@/components/ui/base/BaseButton.vue'

interface Props {
    editor: Editor | undefined
}

const props = defineProps<Props>()

interface EditorRange {
    from: number
    to: number
}

const is_open = ref(false)
const link_text = ref('')
const link_url = ref('')
const link_title = ref('')
const url_touched = ref(false)
const is_editing_link = ref(false)
const link_range = ref<EditorRange | null>(null)

function is_valid_url(value: string): boolean {
    try {
        const parsed = new URL(value)
        return parsed.protocol === 'http:' || parsed.protocol === 'https:' || parsed.protocol === 'mailto:'
    } catch {
        return false
    }
}

const url_error = computed(() => {
    if (!url_touched.value) return undefined
    const url = link_url.value.trim()
    if (!url) return undefined // an empty URL is valid — it just means "no link"
    return is_valid_url(url) ? undefined : 'Enter a valid URL (e.g. https://example.com)'
})

// Keeps a double-click's trailing/leading space out of the stored range.
function trim_range(doc: ProseMirrorNode, from: number, to: number) {
    const raw = doc.textBetween(from, to, '\n')
    const leading = raw.length - raw.trimStart().length
    const trailing = raw.length - raw.trimEnd().length
    return { from: from + leading, to: to - trailing, text: raw.trim() }
}

function open() {
    const editor = props.editor
    if (!editor) return
    url_touched.value = false
    is_editing_link.value = editor.isActive('link')

    if (is_editing_link.value) {
        editor.chain().extendMarkRange('link').run()
        const { from, to } = editor.state.selection
        const trimmed = trim_range(editor.state.doc, from, to)
        link_range.value = { from: trimmed.from, to: trimmed.to }
        link_text.value = trimmed.text
        const attrs = editor.getAttributes('link')
        link_url.value = attrs.href ?? ''
        link_title.value = attrs.title ?? ''
    } else {
        const { from, to } = editor.state.selection
        const trimmed = trim_range(editor.state.doc, from, to)
        link_range.value = { from: trimmed.from, to: trimmed.to }
        link_text.value = trimmed.text
        link_url.value = ''
        link_title.value = ''
    }

    is_open.value = true
}

function close() {
    is_open.value = false
    props.editor?.commands.focus()
}

function apply_link() {
    const url = link_url.value.trim()
    url_touched.value = true
    if (url && !is_valid_url(url)) return
    const editor = props.editor
    if (!editor || !link_range.value) return

    const { from, to } = link_range.value
    const text = link_text.value.trim()

    if (!url) {
        // Empty URL = no link. unsetMark is needed or the new text keeps the old link mark.
        if (text) {
            editor
                .chain()
                .focus()
                .insertContentAt({ from, to }, text)
                .setTextSelection({ from, to: from + text.length })
                .unsetMark('link')
                .run()
        } else {
            editor.chain().focus().deleteRange({ from, to }).run()
        }
    } else {
        const title = link_title.value.trim()
        editor
            .chain()
            .focus()
            .insertContentAt({ from, to }, {
                type: 'text',
                text: text || url,
                marks: [{ type: 'link', attrs: { href: url, title: title || null } }],
            })
            .run()
    }

    close()
}

function remove_link() {
    props.editor?.chain().focus().extendMarkRange('link').unsetLink().run()
    close()
}

defineExpose({ open })
</script>
