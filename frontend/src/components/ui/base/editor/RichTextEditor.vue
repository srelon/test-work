<template>
    <div class="flex flex-col gap-1.5">
        <label v-if="label" class="text-sm font-medium text-neutral-700">{{ label }}</label>

        <div class="rounded-md border bg-white" :class="display_error ? 'border-red-400' : 'border-neutral-300'">
            <div class="flex flex-wrap items-center gap-1 rounded-t-md border-b border-neutral-200 bg-neutral-50 p-1.5">
                <button
                    v-for="tool in toolbar_buttons"
                    :key="tool.key"
                    type="button"
                    class="flex h-8 min-w-8 cursor-pointer items-center justify-center rounded px-2 text-sm transition-colors"
                    :class="tool.active ? 'bg-neutral-900 text-white' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900'"
                    :title="tool.title"
                    @click="tool.run"
                >
                    <span v-if="tool.key === 'bold'" class="font-bold">B</span>
                    <span v-else-if="tool.key === 'italic'" class="italic">I</span>
                    <span v-else-if="tool.key === 'code'" class="font-mono text-xs">&lt;/&gt;</span>
                    <svg v-else-if="tool.key === 'link'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" class="h-4 w-4">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m3.44-3.44l1.757-1.757a4.5 4.5 0 116.364 6.364l-1.757 1.757"
                        />
                    </svg>
                </button>
            </div>

            <EditorContent :editor="editor" class="rich-text-editor min-h-32 overflow-y-auto rounded-b-md px-3.5 py-2.5 text-sm text-neutral-900" />
        </div>

        <span v-if="display_error" class="text-xs text-red-500">{{ display_error }}</span>

        <LinkPopover ref="link_popover_ref" :editor="editor" />
    </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useField } from 'vee-validate'
import { useEditor, EditorContent, mergeAttributes } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import CodeBlock from '@tiptap/extension-code-block'
import Italic from '@tiptap/extension-italic'
import Link from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import LinkPopover from './LinkPopover.vue'

interface Props {
    name: string
    label?: string
    placeholder?: string
}

const props = defineProps<Props>()

const { value: field_value, errorMessage, meta: field_meta, handleBlur } = useField<string>(() => props.name)

const display_error = computed(() => (field_meta.touched ? errorMessage.value : undefined))

const link_popover_ref = ref<InstanceType<typeof LinkPopover> | null>(null)

interface ToolbarButton {
    key: string
    title: string
    active: boolean
    run: () => void
}

const toolbar_buttons = computed<ToolbarButton[]>(() => [
    {
        key: 'bold',
        title: 'Bold',
        active: editor.value?.isActive('bold') ?? false,
        run: () => editor.value?.chain().focus().toggleBold().run(),
    },
    {
        key: 'italic',
        title: 'Italic',
        active: editor.value?.isActive('italic') ?? false,
        run: () => editor.value?.chain().focus().toggleItalic().run(),
    },
    {
        key: 'code',
        title: 'Code',
        active: editor.value?.isActive('codeBlock') ?? false,
        run: () => editor.value?.chain().focus().toggleCodeBlock().run(),
    },
    {
        key: 'link',
        title: 'Link',
        active: editor.value?.isActive('link') ?? false,
        run: () => link_popover_ref.value?.open(),
    },
])

// Renders as <i> instead of StarterKit's default <em> to match the allowed tag set exactly.
const ItalicAsI = Italic.extend({
    parseHTML() {
        return [{ tag: 'i' }, { tag: 'em' }]
    },
    renderHTML({ HTMLAttributes }) {
        return ['i', mergeAttributes(HTMLAttributes), 0]
    },
})

// Renders as a bare <code> instead of <pre><code> — same whitespace-preserving
// codeBlock node, just serialized to match the allowed tag set exactly.
const CodeBlockAsCode = CodeBlock.extend({
    parseHTML() {
        return [{ tag: 'code', preserveWhitespace: 'full' }]
    },
    renderHTML({ HTMLAttributes }) {
        return ['code', mergeAttributes(this.options.HTMLAttributes, HTMLAttributes), 0]
    },
})

// Adds a `title` attribute, which the base Link extension doesn't track.
const LinkWithTitle = Link.extend({
    // Always exclusive, so typing at a link's edge doesn't keep extending it.
    inclusive() {
        return false
    },
    addAttributes() {
        return {
            ...this.parent?.(),
            title: {
                default: null,
                parseHTML: (element: HTMLElement) => element.getAttribute('title'),
                renderHTML: (attributes: { title?: string | null }) => (attributes.title ? { title: attributes.title } : {}),
            },
        }
    },
})

let skip_update = false

const editor = useEditor({
    content: field_value.value || '',
    extensions: [
        StarterKit.configure({
            heading: false,
            bulletList: false,
            orderedList: false,
            listItem: false,
            blockquote: false,
            codeBlock: false,
            strike: false,
            horizontalRule: false,
            italic: false,
            link: false,
            code: false,
        }),
        CodeBlockAsCode,
        ItalicAsI,
        LinkWithTitle.configure({
            openOnClick: false,
            HTMLAttributes: {
                target: null,
                rel: null,
            },
        }),
        Placeholder.configure({
            placeholder: props.placeholder,
        }),
    ],
    editorProps: {
        attributes: {
            class: 'rich-text-editor-content focus:outline-none',
        },
        handleClick(view, _pos, event) {
            const target = event.target as HTMLElement
            const link_el = target.closest('a')
            if (link_el && editor.value) {
                event.preventDefault()
                // A boundary click can resolve just outside the (now non-inclusive) mark.
                const inside_pos = view.posAtDOM(link_el, 0) + 1
                editor.value.commands.setTextSelection(inside_pos)
                link_popover_ref.value?.open()
                return true
            }
            return false
        },
        // Force plain-text paste so markup-looking pasted text can't become real marks.
        handlePaste(view, event) {
            const text = event.clipboardData?.getData('text/plain')
            if (text === undefined) return false
            event.preventDefault()
            const { state, dispatch } = view
            dispatch(state.tr.insertText(text, state.selection.from, state.selection.to))
            return true
        },
    },
    onUpdate({ editor: instance }) {
        if (skip_update) return
        field_value.value = instance.getHTML()
    },
    onBlur() {
        handleBlur()
    },
})

watch(field_value, (val) => {
    if (!editor.value) return
    const next = val || ''
    if (editor.value.getHTML() !== next) {
        skip_update = true
        editor.value.commands.setContent(next, { emitUpdate: false })
        skip_update = false
    }
})

onBeforeUnmount(() => editor.value?.destroy())
</script>

<style scoped>
.rich-text-editor :deep(.rich-text-editor-content) {
    min-height: 7.5rem;
}

.rich-text-editor :deep(p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    float: left;
    height: 0;
    color: #a3a3a3;
    pointer-events: none;
}

.rich-text-editor :deep(a) {
    color: #2563eb;
    text-decoration: underline;
    cursor: pointer;
}

.rich-text-editor :deep(code) {
    display: block;
    overflow-x: auto;
    border-radius: 0.375rem;
    background-color: #2b2b2b;
    color: #ffc66d;
    padding: 0.5rem 0.75rem;
    margin: 0.25rem 0;
    font-family: ui-monospace, monospace;
    font-size: 0.85em;
    line-height: 1.5;
    white-space: pre-wrap;
    word-break: break-word;
}
</style>
